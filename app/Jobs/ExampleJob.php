<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// use Illuminate\Support\Facades\Context;

/**
 * ============================================================
 * ПОЛНЫЙ REFERENCE JOB ДЛЯ СОБЕСЕДОВАНИЯ ПО LARAVEL
 * ============================================================
 *
 * ShouldQueue — маркер того, что job выполняется АСИНХРОННО (через queue worker)
 *                    Без него job выполнится СИНХРОННО в текущем процессе
 *
 * ShouldBeUnique — гарантирует что в очереди не будет дублей этого job
 *                    (пока job в очереди или выполняется, повторный dispatch игнорируется)
 *
 * ShouldBeUniqueUntilProcessing — Блок держится ТОЛЬКО ПОКА Job В ОЧЕРЕДИ.
 *
 * ShouldBeEncrypted — payload job будет зашифрован в хранилище очереди
 *                     (полезно для персональных данных — GDPR и т.д.)
 */
class LearnJob implements ShouldQueue, ShouldBeUnique, ShouldBeEncrypted
{
    // Позволяет Job быть частью Bus::batch()
    use Batchable;

    // Все трейты ниже спрятаны в одном Queueable и используются обязательно!

    // Даёт статические методы: dispatch(), dispatchSync(), dispatchAfterResponse()
    use Dispatchable;

    // Даёт доступ к $this->attempts(), $this->delete(), $this->release(), $this->fail()
    use InteractsWithQueue;

    // Даёт свойства: $connection, $queue, $delay, $afterCommit и chain-методы
    use Queueable;

    // Автоматически сериализует Eloquent модели по ID и десериализует обратно
    // Важно: сохраняется только ID, модель загружается заново при выполнении
    use SerializesModels;

    // ============================================================
    // ОСНОВНЫЕ СВОЙСТВА ОЧЕРЕДИ
    // ============================================================

    /**
     * Имя соединения (connection) — какой драйвер использовать.
     * Определяется в config/queue.php: redis, database, sqs, beanstalkd, sync
     */
    public $connection = 'redis';

    /**
     * Имя очереди (queue/tube) внутри connection.
     * Worker слушает конкретную очередь: php artisan queue:work --queue=high,default,low
     * Приоритет определяется порядком в --queue (left = highest priority).
     */
    public $queue = 'high';

    /**
     * Задержка перед выполнением (в секундах).
     * Job попадёт в очередь, но worker возьмёт его не раньше чем через delay.
     */
    public $delay = 0; // Можно передать \DateTime или Carbon

    // ============================================================
    // RETRY / TIMEOUT / BACKOFF
    // ============================================================

    /**
     * Максимальное количество попыток выполнения.
     * После превышения — job уходит в failed_jobs таблицу.
     *
     * ⚠️ Взаимоисключающе с $retryUntil (метод retryUntil()).
     * Если задан retryUntil, tries игнорируется.
     */
    public int $tries = 3;

    /**
     * Максимальное количество ИСКЛЮЧЕНИЙ (не попыток!).
     * Разница: если job делает release() — это попытка, но не exception.
     * $maxExceptions считает только реальные throw.
     */
    public int $maxExceptions = 2;

    /**
     * Таймаут выполнения job в секундах.
     * Если job работает дольше — worker убьёт процесс.
     *
     * ⚠️ Требует расширение pcntl (не работает на Windows).
     * ⚠️ Должен быть МЕНЬШЕ чем retry_after в config/queue.php,
     *     иначе job может быть взят повторно другим worker'ом.
     */
    public int $timeout = 120;

    /**
     * Backoff — задержка (в секундах) между повторными попытками.
     *
     * Варианты:
     *   public $backoff = 10;           // Фиксированная: 10 сек между попытками
     *   public $backoff = [10, 30, 60]; // Прогрессивная: 1-я retry через 10с, 2-я через 30с, 3-я через 60с
     *
     * Можно также определить как метод для динамической логики.
     */
    public array $backoff = [10, 30, 60];

    /**
     * Уникальный ID для предотвращения дублей.
     * По умолчанию = FQCN класса. Переопределяем для уникальности по параметру.
     *
     * Пример: если $orderId = 42, то в очереди может быть только один
     * ExampleJob для заказа #42.
     */
    public int $uniqueId;

    // ============================================================
    // UNIQUENESS (ShouldBeUnique)
    // ============================================================
    /**
     * Время жизни уникальной блокировки (в секундах).
     * После истечения — можно dispatch снова, даже если job ещё в очереди.
     * По умолчанию блокировка снимается когда job завершится.
     */
    public int $uniqueFor = 3600; // 1 час

    /**
     * Кэш-драйвер для хранения lock'а уникальности.
     * По умолчанию — default cache driver из config/cache.php.
     */
    public string $uniqueVia;

    /**
     * Если true — job dispatched только ПОСЛЕ коммита транзакции БД.
     * Предотвращает ситуацию когда job берётся worker'ом, а данные ещё не закоммичены.
     *
     * Можно установить глобально: config/queue.php → 'after_commit' => true
     */
    public $afterCommit = true;

    // ============================================================
    // BATCH & CHAIN
    // ============================================================
    /**
     * Удалить job из очереди если модель (SerializesModels) была удалена
     * к моменту выполнения. Без этого — выбросит ModelNotFoundException.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * ⚠️ ВАЖНО ДЛЯ СОБЕСЕДОВАНИЯ:
     * Всё что передаётся в конструктор — СЕРИАЛИЗУЕТСЯ в payload очереди.
     * - Eloquent модели → сериализуются как ID (благодаря SerializesModels)
     * - Closures → НЕ сериализуются (будет ошибка)
     * - Большие объекты → увеличивают payload, лучше передавать ID
     *
     *  ⚠️ НЮАНСЫ СЕРИАЛИЗАЦИИ (Вопрос на Senior):
     *  1. Public свойства: Сериализуются в payload (JSON).
     *  2. Protected/Private свойства: Также сериализуются (начиная с PHP 7.4+ и новых версий Laravel).
     *  3. Closures (анонимные функции): НЕ сериализуются! Вызовут Exception.
     *  4. Если передать `Order $order`, трейт `SerializesModels` сохранит только `class` и `id`.
     *  При выполнении Laravel сам сделает `Order::findOrFail($id)` в методе `__wakeup()`.
     */
    public function __construct(
        public readonly int   $orderId,
        public readonly array $options = [],
    )
    {
        // Можно переопределить queue/connection прямо в конструкторе:
        // $this->onQueue('critical');
        // $this->onConnection('sqs');
        // $this->delay(now()->addMinutes(5));
    }

    // ============================================================
    // CONSTRUCTOR
    // ============================================================

    /**
     * Альтернатива $tries — повторять до указанного времени.
     * Полезно когда важнее дедлайн, а не количество попыток.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(12);
    }

    // ============================================================
    // MIDDLEWARE
    // ============================================================

    /**
     * Middleware выполняются ДО handle().
     * Позволяют контролировать выполнение без загромождения бизнес-логики.
     * ⚠️ Важно: задача заблокированная middleware засчитывается за попытку (tries), так как была взята worker.
     */
    public function middleware(): array
    {
        return [
            // Предотвращает одновременное выполнение job'ов с одинаковым ключом.
            // Если другой job с таким ключом уже выполняется — текущий вернётся в очередь.
            (new WithoutOverlapping($this->orderId))
                ->releaseAfter(60)      // Через сколько секунд попробовать запустить job еще раз
                ->dontRelease()         // Или: не возвращать в очередь, просто удалить
                ->expireAfter(300),     // Lock автоматически истекает через 5 минут если не был снят. Например: job завис.

            // Rate limiting — ограничение частоты выполнения.
            // Лимит определяется в AppServiceProvider через RateLimiter::for('exports', ...)
            new RateLimited('exports'),

            // Троттлинг исключений — если job падает слишком часто,
            // увеличивает задержку перед retry.
            (new ThrottlesExceptions(maxAttempts: 5, decaySeconds: 3600))
                ->backoff(5),           // Начальная задержка 5 минут
        ];
    }

    // ============================================================
    // HANDLE — ОСНОВНАЯ ЛОГИКА
    // ============================================================

    /**
     * ⚠️ ВАЖНО: handle() поддерживает Dependency Injection через Service Container.
     * Можно typehint любые сервисы — Laravel их автоматически зарезолвит.
     */
    public function handle(/* OrderService $orderService */): void
    {
        // --- Полезные методы из InteractsWithQueue ---

        // Текущая попытка (начинается с 1)
        $attempt = $this->attempts();

        // Проверка: был ли job уже удалён/released другим процессом
        if ($this->job->isDeleted() || $this->job->isReleased()) {
            return;
        }

        // Получить UUID job'а (полезно для логирования)
        $jobId = $this->job->uuid();

        // --- Проверка: job выполняется в составе Batch? ---
        if ($this->batch()) {
            // $this->batch()->id          — ID батча
            // $this->batch()->name        — Имя батча
            // $this->batch()->progress()  — Прогресс (0-100)
            // $this->batch()->cancelled() — Был ли батч отменён

            if ($this->batch()->cancelled()) {
                return; // Не выполнять если батч отменён
            }
        }

        // === БИЗНЕС-ЛОГИКА ===
        try {
            Log::info("Processing order #{$this->orderId}, attempt #{$attempt}");

            // ... ваша логика ...

        } catch (Throwable $e) {
            // Вариант 1: Вернуть в очередь с задержкой (НЕ считается exception)
            // $this->release(delay: 30);

            // Вариант 2: Пометить как FAILED немедленно (без дальнейших retry)
            // $this->fail($e);

            // Вариант 3: Удалить из очереди без пометки failed
            // $this->delete();

            // Вариант 4: Просто выбросить — пойдёт стандартный retry по $tries/$backoff
            throw $e;
        }
    }

    // ============================================================
    // LIFECYCLE HOOKS
    // ============================================================

    /**
     * Вызывается когда job ОКОНЧАТЕЛЬНО провалился (все попытки исчерпаны).
     * Запись уходит в таблицу failed_jobs.
     *
     * Идеально для: уведомлений, алертов, компенсационной логики.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("Job failed for order #{$this->orderId}: {$exception->getMessage()}");

        // Отправить уведомление, откатить операцию и т.д.
    }

    /**
     * Определяет теги для мониторинга в Laravel Horizon.
     * Horizon группирует и фильтрует job'ы по этим тегам.
     */
    public function tags(): array
    {
        return ['order', "order:{$this->orderId}"];
    }

    /**
     * Имя для отображения в Horizon (вместо FQCN класса).
     */
    public function displayName(): string
    {
        return "ProcessOrder #{$this->orderId}";
    }
}

// ============================================================
// СПОСОБЫ DISPATCH (вызов из контроллера/сервиса)
// ============================================================

/*
 * 1. БАЗОВЫЙ DISPATCH (асинхронно)
 *    ExampleJob::dispatch(orderId: 42);
 *
 * 2. С НАСТРОЙКАМИ ОЧЕРЕДИ
 *    ExampleJob::dispatch(42)
 *        ->onQueue('high')
 *        ->onConnection('redis')
 *        ->delay(now()->addMinutes(5))
 *        ->afterCommit();           // Только после DB commit
 *
 * 3. СИНХРОННОЕ ВЫПОЛНЕНИЕ (игнорирует очередь)
 *    ExampleJob::dispatchSync(42);
 *
 * 4. ПОСЛЕ ОТПРАВКИ HTTP RESPONSE (синхронно, но не блокирует ответ)
 *    ExampleJob::dispatchAfterResponse(42);
 *
 * 5. DISPATCH ЕСЛИ НЕ ДУБЛЬ (без ShouldBeUnique)
 *    ExampleJob::dispatchIf($condition, 42);
 *    ExampleJob::dispatchUnless($condition, 42);
 *
 * 6. CHAIN — последовательное выполнение
 *    Bus::chain([
 *        new ExampleJob(1),
 *        new AnotherJob(2),
 *        new FinalJob(3),
 *    ])->onQueue('high')->dispatch();
 *    // ⚠️ Если любой job в chain падает — остальные НЕ выполняются
 *
 * 7. BATCH — параллельное выполнение с мониторингом
 *    Bus::batch([
 *        new ExampleJob(1),
 *        new ExampleJob(2),
 *        new ExampleJob(3),
 *    ])
 *    ->name('Process Orders')
 *    ->then(fn(Batch $b)    => Log::info('All done!'))     // Все успешно
 *    ->catch(fn(Batch $b, $e) => Log::error('Failed!'))    // Первый fail
 *    ->finally(fn(Batch $b) => Log::info('Batch finished'))// Всегда
 *    ->allowFailures()       // Продолжать даже если часть упала
 *    ->onQueue('high')
 *    ->dispatch();
 *    // ⚠️ Требует миграция: php artisan queue:batches-table
 *
 * 8. DISPATCH ЧЕРЕЗ CLOSURE (анонимные job'ы, без класса)
 *    dispatch(function () {
 *        // логика
 *    })->onQueue('default');
 */

// ============================================================
// КЛЮЧЕВЫЕ ARTISAN КОМАНДЫ
// ============================================================

/*
 * php artisan queue:work                    — Запустить worker (daemon, держит приложение в памяти)
 * php artisan queue:work --queue=high,low   — Слушать конкретные очереди с приоритетом
 * php artisan queue:work --tries=3          — Переопределить tries из CLI
 * php artisan queue:work --timeout=60       — Переопределить timeout
 * php artisan queue:work --sleep=3          — Пауза между проверками пустой очереди
 * php artisan queue:work --max-jobs=100     — Остановить worker после 100 job'ов (для memory leaks)
 * php artisan queue:work --max-time=3600    — Остановить через 1 час
 * php artisan queue:work --memory=128       — Остановить при превышении памяти (MB)
 * php artisan queue:work --stop-when-empty  — Остановить когда очередь пуста
 *
 * php artisan queue:listen                  — Запустить listener (перезагружает app каждый job, медленнее)
 *                                            ⚠️ Используйте для dev, в prod — queue:work
 *
 * php artisan queue:restart                 — Graceful restart всех worker'ов
 *                                            ⚠️ ОБЯЗАТЕЛЬНО после deploy (worker держит старый код!)
 *
 * php artisan queue:retry all               — Перезапустить все failed job'ы
 * php artisan queue:retry {uuid}            — Перезапустить конкретный
 * php artisan queue:forget {uuid}           — Удалить failed job
 * php artisan queue:flush                   — Удалить ВСЕ failed job'ы
 * php artisan queue:prune-failed            — Удалить старые failed (--hours=48)
 * php artisan queue:clear --queue=high      — Очистить очередь
 * php artisan queue:monitor high:100,low:50 — Алерт если в очереди > N job'ов
 *
 * МИГРАЦИИ:
 * php artisan queue:table                   — Миграция для database driver
 * php artisan queue:failed-table            — Таблица failed_jobs
 * php artisan queue:batches-table           — Таблица для Bus::batch()
 */

// ============================================================
// ВОПРОСЫ НА СОБЕСЕДОВАНИИ — ШПАРГАЛКА
// ============================================================

/*
 * Q: Разница queue:work vs queue:listen?
 * A: work — daemon, держит app в памяти (быстро, для prod).
 *    listen — перезагружает framework каждый job (медленно, для dev).
 *    После deploy ОБЯЗАТЕЛЬНО queue:restart для work.
 *
 * Q: Как предотвратить дублирование job'ов?
 * A: ShouldBeUnique + uniqueId + uniqueFor. Использует cache lock.
 *
 * Q: Что такое SerializesModels и зачем?
 * A: Сериализует Eloquent по ID, десериализует при выполнении.
 *    Избегаем хранения тяжёлых объектов. Но модель может быть удалена
 *    между dispatch и handle → $deleteWhenMissingModels.
 *
 * Q: retry_after vs timeout?
 * A: timeout — сколько job может работать. retry_after (config/queue.php) —
 *    через сколько считать job зависшим и отдать другому worker'у.
 *    ПРАВИЛО: timeout < retry_after, иначе job будет взят дважды.
 *
 * Q: Как обработать failed jobs?
 * A: метод failed() в job, глобально — Queue::failing() в провайдере,
 *    таблица failed_jobs + artisan queue:retry.
 *
 * Q: Batch vs Chain?
 * A: Chain — последовательно, один за другим. Падение = остановка цепочки.
 *    Batch — параллельно, с мониторингом прогресса и callback'ами.
 *
 * Q: Как масштабировать?
 * A: Несколько worker'ов (Supervisor), разные очереди по приоритету,
 *    Laravel Horizon для Redis (автобалансировка, мониторинг).
 *
 * Q: afterCommit — зачем?
 * A: Job может быть взят worker'ом ДО коммита транзакции.
 *    afterCommit гарантирует dispatch только после коммита.
 *
 * Q: Supervisor — зачем?
 * A: Автоматически перезапускает worker если он упал.
 *    Конфиг: /etc/supervisor/conf.d/laravel-worker.conf
 */
