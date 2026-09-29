<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Enums\AssessmentScope;
use App\Enums\Availability;
use App\Enums\BudgetType;
use App\Enums\CompanySize;
use App\Enums\ContentAudience;
use App\Enums\ContentPurpose;
use App\Enums\ContractStatus;
use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use App\Enums\ExperienceLevel;
use App\Enums\FreelancerFieldStatus;
use App\Enums\LearningContentType;
use App\Enums\MentoringStyle;
use App\Enums\MentorshipProgramStatus;
use App\Enums\MentorshipSessionStatus;
use App\Enums\MentorshipSessionType;
use App\Enums\MentorshipTrack;
use App\Enums\MessageStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ModerationStatus;
use App\Enums\PortfolioMediaType;
use App\Enums\PostingType;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\RoleName;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\UserStatus;
use App\Enums\ViolationAction;
use App\Enums\ViolationSource;
use App\Enums\ViolationType;
use App\Enums\WithdrawalStatus;
use App\Models\Assessment;
use App\Models\BankCard;
use App\Models\Category;
use App\Models\CategoryBudgetRange;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\EmployerSubscription;
use App\Models\MentorshipProgram;
use App\Models\Milestone;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Skill;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SkillExams;
use App\Services\WalletLedger;
use App\Support\BankCard as CardNumber;
use App\Support\PersianText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Realistic Persian demo data for local development, so every dashboard has something to show.
 *
 * Accounts (password "password"):
 *   admin (all admin permissions), support, finance, content (suspended) — staff with limited access
 *   mentor, mentor2 — verified mentors
 *   freelancer (beginner), hossein, zahra, fatemeh, ali, narges (suspended after a violation)
 *   employer, shop, startup, agency
 *
 * The root super admin (mahan) is created by SuperAdminSeeder from .env, not here.
 * Money moves through WalletLedger, so every wallet equals the sum of its ledger rows, and the
 * clock is moved back while seeding so charts and "3 days ago" labels look lived-in.
 * Runs once: when the demo employer already exists it does nothing.
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /**
     * A 16×10 brand-teal PNG, used when the GD extension is missing.
     */
    private const FALLBACK_PNG = 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAKCAIAAAAy3EnLAAAAEklEQVR42mNgyG4iDY1qGJoaAHuNlCEpAvXoAAAAAElFTkSuQmCC';

    /**
     * The demo timeline follows the clock of the platform's users.
     */
    private const LOCAL_TIMEZONE = 'Asia/Tehran';

    private CarbonImmutable $now;

    private WalletLedger $ledger;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, Skill> */
    private array $skills = [];

    /** @var array<string, Plan> */
    private array $plans = [];

    /** @var array<string, Project> */
    private array $projects = [];

    /** @var array<int, Ticket> mentoring tickets opened by hires, keyed by contract id */
    private array $contractTickets = [];

    /** @var array<string, Contract> */
    private array $contracts = [];

    public function run(): void
    {
        if (User::where('username', 'employer')->exists()) {
            $this->command?->info('Demo data already exists - skipped.');

            return;
        }

        $this->now = CarbonImmutable::now();
        $this->ledger = app(WalletLedger::class);
        $this->categories = Category::all()->keyBy('slug')->all();
        $this->skills = Skill::all()->keyBy('slug')->all();

        try {
            $this->staff();
            $this->catalog();
            $this->mentors();
            $this->freelancers();
            $this->employers();
            $this->marketplace();
            $this->mentoring();
            $this->withdrawals();
            $this->exams();
            $this->learningContent();
            $this->violations();
            $this->lastLogins();
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Demo accounts are ready: admin, mentor, freelancer, employer ... (password: '.self::PASSWORD.').');
    }

    // ---------------------------------------------------------------- people

    private function staff(): void
    {
        $admin = $this->person('admin', 'سارا محمدی', RoleName::Admin, 150, '09120000101', 'مدیر عملیات بازارگاه جامپ‌لنسر.');
        $admin->syncPermissions(AdminPermission::values());

        $support = $this->person('support', 'رضا کریمی', RoleName::Support, 120, '09120000102', 'پشتیبانی کاربران و منتورینگ.');
        $support->syncPermissions([AdminPermission::ManageUsers->value, AdminPermission::ManageMentoring->value, AdminPermission::ManageModeration->value, AdminPermission::ManageWithdrawals->value, AdminPermission::ManageExams->value]);

        $finance = $this->person('finance', 'نگار حسینی', RoleName::Admin, 100, '09120000103', 'امور مالی و قراردادها.');
        $finance->syncPermissions([AdminPermission::ViewFinance->value, AdminPermission::ManageContracts->value, AdminPermission::ManageWithdrawals->value]);

        $content = $this->person('content', 'امیر رضایی', RoleName::Admin, 70, '09120000104', 'تولید محتوای آموزشی.');
        $content->syncPermissions([AdminPermission::ManageContent->value, AdminPermission::ManageCatalog->value]);
        $content->forceFill([
            'status' => UserStatus::Suspended,
            'suspended_at' => $this->now->subDays(3),
            'suspension_reason' => 'مرخصی تا پایان ماه',
        ])->save();
    }

    private function mentors(): void
    {
        $mentor = $this->person('mentor', 'علی نوری', RoleName::Mentor, 140, '09120000201', 'برنامه‌نویس ارشد بک‌اند با ده سال تجربه؛ عاشق کمک به شروع‌کننده‌ها.');
        $mentor->mentorProfile()->first()->forceFill([
            'expertise_summary' => 'لاراول، معماری نرم‌افزار، کدنویسی تمیز و آماده‌سازی تازه‌کارها برای پروژه‌ی واقعی.',
            'years_experience' => 10,
            'mentoring_style' => MentoringStyle::Both,
            'max_mentees' => 6,
            'is_verified' => true,
        ])->save();

        $mentor2 = $this->person('mentor2', 'مریم صادقی', RoleName::Mentor, 110, '09120000202', 'طراح محصول و مربی مهارت‌های نرم.');
        $mentor2->mentorProfile()->first()->forceFill([
            'expertise_summary' => 'طراحی محصول، تولید محتوا و انگیزه‌بخشی برای شروع کار آزاد.',
            'years_experience' => 7,
            'mentoring_style' => MentoringStyle::Motivational,
            'max_mentees' => 4,
            'is_verified' => true,
        ])->save();
    }

    private function freelancers(): void
    {
        $profiles = [
            'freelancer' => ['محمد احمدی', 70, '09120000301', 'تازه وارد دنیای فریلنسری شده‌ام و روی وردپرس و لاراول تمرین می‌کنم.', ExperienceLevel::Beginner, 'توسعه‌دهنده‌ی تازه‌کار وردپرس و لاراول', 62, 180_000],
            'hossein' => ['حسین مرادی', 170, '09120000302', 'چهار سال سابقه‌ی توسعه‌ی وب با لاراول و ری‌اکت.', ExperienceLevel::Intermediate, 'توسعه‌دهنده‌ی فول‌استک لاراول', 88, 450_000],
            'zahra' => ['زهرا کاظمی', 100, '09120000303', 'طراح رابط کاربری؛ طراحی ساده و کاربردی را دوست دارم.', ExperienceLevel::Junior, 'طراح UI/UX با فیگما', 74, 300_000],
            'fatemeh' => ['فاطمه جعفری', 55, '09120000304', 'نویسنده‌ی محتوا و مترجم انگلیسی.', ExperienceLevel::Beginner, 'تولیدکننده‌ی محتوای سئو', 48, 120_000],
            'ali' => ['علی رحیمی', 11, '09120000305', null, ExperienceLevel::Beginner, 'علاقه‌مند به فرانت‌اند و سئو', 35, null],
            'narges' => ['نرگس یوسفی', 14, '09120000306', 'مدیر شبکه‌های اجتماعی کسب‌وکارهای کوچک.', ExperienceLevel::Junior, 'بازاریاب اینستاگرام', 57, 200_000],
            'sepideh' => ['سپیده کریمی', 17, '09120000307', null, ExperienceLevel::Beginner, null, 10, null],
            'reza' => ['رضا امیری', 3, null, null, ExperienceLevel::Beginner, null, 5, null],
        ];

        foreach ($profiles as $username => [$name, $joined, $phone, $bio, $level, $headline, $readiness, $rate]) {
            $user = $this->person($username, $name, RoleName::Freelancer, $joined, $phone, $bio);
            $user->freelancerProfile()->first()->forceFill([
                'headline' => $headline,
                'level' => $level,
                'readiness_score' => $readiness,
                'hourly_rate' => $rate,
                'availability' => $username === 'hossein' ? Availability::Busy : Availability::Available,
                'onboarding_completed' => ! in_array($username, ['ali', 'sepideh', 'reza'], true),
            ])->save();
        }

        // Work fields: a first field below the exam level is active at once; the others went through an exam.
        $this->field('freelancer', 'programming-tech', ExperienceLevel::Beginner, primary: true, status: FreelancerFieldStatus::Active, daysAgo: 68);
        $this->field('freelancer', 'design-creative', ExperienceLevel::Beginner, primary: false, status: FreelancerFieldStatus::PendingExam, daysAgo: 4);
        $this->field('hossein', 'programming-tech', ExperienceLevel::Intermediate, primary: true, status: FreelancerFieldStatus::Active, daysAgo: 165);
        $this->field('zahra', 'design-creative', ExperienceLevel::Junior, primary: true, status: FreelancerFieldStatus::Active, daysAgo: 98);
        $this->field('fatemeh', 'writing-translation', ExperienceLevel::Beginner, primary: true, status: FreelancerFieldStatus::Active, daysAgo: 54);
        $this->field('ali', 'programming-tech', ExperienceLevel::Beginner, primary: true, status: FreelancerFieldStatus::Active, daysAgo: 10);
        $this->field('ali', 'digital-marketing', ExperienceLevel::Beginner, primary: false, status: FreelancerFieldStatus::PendingExam, daysAgo: 2);
        $this->field('narges', 'digital-marketing', ExperienceLevel::Junior, primary: true, status: FreelancerFieldStatus::Active, daysAgo: 13);
        $this->field('narges', 'writing-translation', ExperienceLevel::Junior, primary: false, status: FreelancerFieldStatus::Rejected, daysAgo: 10);

        $this->portfolio();
    }

    private function employers(): void
    {
        $companies = [
            'employer' => ['کامران شریفی', 160, '09120000401', 'شرکت نرم‌افزاری آرتا', CompanySize::ElevenToFifty, 'نرم‌افزار و خدمات وب', 'https://arta.example'],
            'shop' => ['لیلا امینی', 60, '09120000402', 'فروشگاه اینترنتی نیلوفر', CompanySize::Solo, 'فروشگاه اینترنتی لوازم خانگی', 'https://niloofar.example'],
            'startup' => ['پویا توکلی', 95, '09120000403', 'استارتاپ رهنما', CompanySize::TwoToTen, 'گردشگری و سفر', 'https://rahnama.example'],
            'agency' => ['مهسا نظری', 200, '09120000404', 'آژانس دیجیتال پیکسل', CompanySize::FiftyOneToTwoHundred, 'تبلیغات و بازاریابی', 'https://pixel.example'],
        ];

        foreach ($companies as $username => [$name, $joined, $phone, $company, $size, $industry, $website]) {
            $user = $this->person($username, $name, RoleName::Employer, $joined, $phone, "کارفرما از طرف {$company}.");
            $user->employerProfile()->first()->forceFill([
                'company_name' => $company,
                'company_size' => $size,
                'industry' => $industry,
                'website' => $website,
                'open_to_beginners' => true,
            ])->save();
        }
    }

    private function person(string $username, string $name, RoleName $role, int $joinedDaysAgo, ?string $phone, ?string $bio): User
    {
        return $this->at($joinedDaysAgo, function () use ($username, $name, $role, $phone, $bio): User {
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => "{$username}@jumplancer.test",
                'phone' => $phone,
                'password' => self::PASSWORD,
                'bio' => $bio,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Role::findOrCreate($role->value, 'web'));
            $user->ensureRoleProfiles();

            return $this->users[$username] = $user;
        }, hour: 9 + strlen($username) % 8);
    }

    private function field(string $username, string $category, ExperienceLevel $level, bool $primary, FreelancerFieldStatus $status, int $daysAgo): void
    {
        $this->at($daysAgo, fn () => $this->users[$username]->freelancerFields()->create([
            'category_id' => $this->categories[$category]->id,
            'claimed_level' => $level,
            'is_primary' => $primary,
            'exam_required' => ! $primary || $level->isAtLeast(ExperienceLevel::Intermediate),
            'exam_fee_required' => ! $primary,
            'status' => $status,
            'verified_at' => $status === FreelancerFieldStatus::Active ? now() : null,
        ]));
    }

    // ---------------------------------------------------------------- skill exams

    /**
     * Exams from the exam builder and attempts at them, taken through the real exam flow: passes
     * (verified skills), a fail, and a paid attempt voided for leaving the page twice.
     */
    private function exams(): void
    {
        $admin = $this->users['admin'];
        $support = $this->users['support'];

        PlatformSetting::where('setting_key', 'extra_field_exam_fee')->update(['setting_value' => '150000', 'updated_by' => $admin->id]);

        $bank = [
            'laravel' => ['web-development', 'آزمون مقدماتی لاراول', 'مسیرها، کنترلرها، Eloquent و Blade؛ برای کسی که اولین پروژه‌ی لاراولی‌اش را تحویل داده.', 15, 100, 60, true, $admin, [
                ['کدام دستور مایگریشن‌های پروژه را اجرا می‌کند؟', 'دستورهای لاراول با artisan اجرا می‌شوند.', ['php artisan migrate', 'php artisan serve', 'composer dump-autoload', 'npm run build'], 0],
                ['مسیرهای وب در کدام فایل تعریف می‌شوند؟', null, ['routes/web.php', 'config/app.php', 'app/Http/Kernel.php', 'resources/views/web.blade.php'], 0],
                ['برای جلوگیری از Mass Assignment در مدل چه چیزی تعریف می‌شود؟', null, ['$casts', '$fillable یا $guarded', '$table', '$with'], 1],
                ['کدام متد Eloquent رکوردها را همراه رابطه‌شان یک‌جا بارگذاری می‌کند؟', 'به مشکل N+1 فکر کن.', ['load()', 'with()', 'get()', 'pluck()'], 1],
                ['در Blade خروجی امن (escape‌شده) با کدام نحو چاپ می‌شود؟', null, ['{!! $name !!}', '{{ $name }}', '<?= $name ?>', '@echo($name)'], 1],
                ['اعتبارسنجی درخواست در یک کلاس جدا با کدام دستور ساخته می‌شود؟', null, ['make:request', 'make:rule', 'make:policy', 'make:middleware'], 0],
            ]],
            'wordpress' => ['web-development', 'آزمون وردپرس برای تازه‌کارها', 'قالب، افزونه و تنظیمات پایه‌ی یک سایت وردپرسی.', 10, 100, 50, true, $admin, [
                ['تغییرات قالب را کجا بنویسیم که با به‌روزرسانی پاک نشود؟', null, ['مستقیم در قالب اصلی', 'در یک قالب فرزند (Child Theme)', 'در wp-config.php', 'در پوشه‌ی uploads'], 1],
                ['اطلاعات اتصال به پایگاه داده در کدام فایل است؟', null, ['functions.php', 'wp-config.php', 'index.php', '.htaccess'], 1],
                ['کدام تابع برای افزودن استایل به قالب توصیه می‌شود؟', 'اسمش با wp_enqueue شروع می‌شود.', ['wp_enqueue_style', 'add_style', 'include_css', 'wp_head_css'], 0],
                ['پیوندهای یکتا (Permalinks) از کدام بخش پیشخوان تنظیم می‌شوند؟', null, ['نمایش', 'تنظیمات', 'ابزارها', 'کاربران'], 1],
                ['برای ساخت فرم تماس معمولاً چه می‌کنیم؟', null, ['کد PHP در هسته', 'یک افزونه‌ی فرم', 'ویرایش پایگاه داده', 'تغییر .htaccess'], 1],
            ]],
            'figma' => ['ui-ux', 'آزمون مبانی فیگما', 'Auto Layout، کامپوننت‌ها و آماده‌سازی خروجی برای برنامه‌نویس.', 12, 20, 14, true, $support, [
                ['برای چیدمان خودکار و واکنش‌گرا در فیگما از چه استفاده می‌شود؟', null, ['Auto Layout', 'Mask', 'Boolean groups', 'Plugins'], 0],
                ['نسخه‌های مختلف یک دکمه (عادی، غیرفعال، هاور) را با چه می‌سازیم؟', null, ['Frame', 'Variants', 'Slice', 'Grid'], 1],
                ['رنگ‌ها و فونت‌های مشترک را کجا تعریف می‌کنیم؟', 'تا با یک تغییر، همه‌جا عوض شوند.', ['Styles / Variables', 'Comments', 'Pages', 'Layers'], 0],
                ['برنامه‌نویس اندازه‌ها و CSS را از کدام حالت می‌بیند؟', null, ['Prototype', 'Dev Mode (Inspect)', 'Presentation', 'FigJam'], 1],
                ['برای خروجی گرفتن آیکن‌ها کدام قالب مناسب‌تر است؟', null, ['SVG', 'JPG', 'GIF', 'BMP'], 0],
            ]],
            'technical-seo' => ['seo', 'آزمون سئوی تکنیکال', 'نقشه‌ی سایت، robots و سرعت بارگذاری. (در حال بازبینی سؤال‌ها)', 10, 100, 70, false, $admin, [
                ['فایل robots.txt چه کاری انجام می‌دهد؟', null, ['رتبه را بالا می‌برد', 'به خزنده‌ها می‌گوید کجا را نخزند', 'سایت را سریع می‌کند', 'بک‌لینک می‌سازد'], 1],
                ['نقشه‌ی سایت XML برای چیست؟', null, ['معرفی صفحه‌ها به موتور جستجو', 'طراحی منو', 'پشتیبان‌گیری', 'امنیت فرم‌ها'], 0],
                ['کدام تگ نسخه‌ی اصلی یک صفحه‌ی تکراری را مشخص می‌کند؟', null, ['noindex', 'canonical', 'hreflang', 'nofollow'], 1],
                ['معیار LCP به چه چیزی مربوط است؟', null, ['سرعت بارگذاری بزرگ‌ترین محتوا', 'تعداد لینک‌ها', 'طول عنوان', 'تعداد کلمات'], 0],
            ]],
        ];

        $exams = [];

        foreach ($bank as $skill => [$category, $title, $description, $minutes, $total, $pass, $active, $author, $questions]) {
            $exams[$skill] = $this->at(40, function () use ($skill, $category, $title, $description, $minutes, $total, $pass, $active, $author, $questions): Assessment {
                $exam = new Assessment([
                    'scope' => AssessmentScope::Skill,
                    'category_id' => $this->categories[$category]->id,
                    'skill_id' => $this->skills[$skill]->id,
                    'title' => $title,
                    'description' => $description,
                    'time_limit_minutes' => $minutes,
                    'total_score' => $total,
                    'pass_score' => $pass,
                    'is_active' => $active,
                ]);
                $exam->forceFill(['created_by' => $author->id])->save();

                foreach ($questions as $position => [$body, $hint, $options, $correct]) {
                    $question = $exam->questions()->create(['body' => $body, 'hint' => $hint, 'sort_order' => $position + 1]);

                    foreach ($options as $index => $option) {
                        $question->options()->create(['body' => $option, 'is_correct' => $index === $correct, 'sort_order' => $index + 1]);
                    }
                }

                return $exam->load('questions.options');
            });
        }

        // [freelancer, exam, days ago, right answers, left the page (times)]
        $attempts = [
            ['hossein', 'laravel', 30, 6, 0],
            ['freelancer', 'laravel', 21, 5, 0],
            ['zahra', 'figma', 18, 5, 1],
            ['freelancer', 'wordpress', 6, 2, 0],
            ['ali', 'laravel', 2, 2, 0],
            ['freelancer', 'figma', 3, 3, 2],
        ];

        $exams = collect($exams);
        $service = app(SkillExams::class);

        foreach ($attempts as [$username, $skill, $daysAgo, $right, $left]) {
            $freelancer = $this->users[$username];
            $exam = $exams[$skill];

            $this->at($daysAgo, function () use ($service, $freelancer, $exam, $right, $left): void {
                if ((int) $freelancer->ensureWallet()->fresh()->balance < 150_000) {
                    $this->ledger->deposit($freelancer, 200_000);
                }

                $attempt = $service->start($freelancer, $exam);
                $service->recordAnswers($attempt, $exam->questions->mapWithKeys(fn ($question, $index) => [
                    $question->id => $question->options->firstWhere('is_correct', $index < $right)->id,
                ])->all());

                for ($i = 0; $i < $left; $i++) {
                    $attempt = $service->recordViolation($attempt, 'hidden');
                }

                Carbon::setTestNow(now()->addMinutes(min(9, $exam->time_limit_minutes - 1)));
                $service->finish($attempt);
            });
        }
    }

    // ---------------------------------------------------------------- catalog and plans

    private function catalog(): void
    {
        $admin = $this->users['admin'];

        $ranges = [
            ['programming-tech', BudgetType::Fixed, 2_000_000, null],
            ['web-development', BudgetType::Fixed, 3_000_000, 300_000_000],
            ['web-development', BudgetType::Hourly, 150_000, 1_500_000],
            ['graphic-design', BudgetType::Fixed, 800_000, 50_000_000],
            ['content-writing', BudgetType::Fixed, 1_000_000, null],
            ['translation', BudgetType::Fixed, 500_000, null],
        ];

        foreach ($ranges as [$slug, $type, $min, $max]) {
            CategoryBudgetRange::updateOrCreate(
                ['category_id' => $this->categories[$slug]->id, 'budget_type' => $type],
                ['min_amount' => $min, 'max_amount' => $max, 'is_active' => true, 'updated_by' => $admin->id],
            );
        }

        $plans = [
            'starter' => ['پلن شروع', 490_000, 3, 90, 'برای کسب‌وکارهای کوچکی که چند پروژه‌ی کوتاه دارند.'],
            'growth' => ['پلن رشد', 1_290_000, 10, 180, 'محبوب‌ترین پلن؛ مناسب تیم‌هایی که مرتب برون‌سپاری می‌کنند.'],
            'pro' => ['پلن حرفه‌ای', 2_900_000, null, 365, 'انتشار پروژه‌ی نامحدود برای آژانس‌ها و شرکت‌ها.'],
        ];

        foreach ($plans as $key => [$name, $price, $quota, $days, $description]) {
            $this->plans[$key] = Plan::create([
                'name' => $name,
                'price' => $price,
                'project_quota' => $quota,
                'duration_days' => $days,
                'description' => $description,
                'is_active' => true,
            ]);
        }

        Plan::create([
            'name' => 'پلن آزمایشی قدیمی',
            'price' => 99_000,
            'project_quota' => 1,
            'duration_days' => 30,
            'description' => 'دیگر فروخته نمی‌شود.',
            'is_active' => false,
        ]);
    }

    // ---------------------------------------------------------------- projects, proposals, contracts and money

    private function marketplace(): void
    {
        $employer = $this->users['employer'];
        $shop = $this->users['shop'];
        $startup = $this->users['startup'];
        $agency = $this->users['agency'];
        // Wallet top-ups through the sandbox gateway.
        $this->at(158, fn () => $this->ledger->deposit($employer, 60_000_000));
        $this->at(118, fn () => $this->ledger->deposit($employer, 40_000_000));
        $this->at(58, fn () => $this->ledger->deposit($shop, 12_000_000));
        $this->at(92, fn () => $this->ledger->deposit($startup, 30_000_000));
        $this->at(31, fn () => $this->ledger->deposit($startup, 5_000_000));
        $this->at(190, fn () => $this->ledger->deposit($agency, 3_000_000));

        // --- employer (Arta): two free projects, then the growth plan -------------------------------
        $p2 = $this->project('p2', $employer, 'web-development', [
            'title' => 'پنل مدیریت فروشگاه اینترنتی با لاراول',
            'description' => "یک پنل مدیریت برای فروشگاه آنلاین لازم داریم: مدیریت محصولات و موجودی، سفارش‌ها، گزارش فروش ماهانه و سطح دسترسی برای کارمندان.\nبک‌اند لاراول و دیتابیس MySQL باشد و ظاهر پنل با بوت‌استرپ.",
            'budget_min' => 40_000_000, 'budget_max' => 50_000_000, 'deadline_days' => 45,
            'status' => ProjectStatus::Completed, 'posting' => PostingType::FreeFirst, 'beginner' => false,
            'skills' => ['laravel', 'mysql', 'bootstrap'],
        ], daysAgo: 150);
        $this->freePostingUsed($employer, 1, 150);

        // The second free posting expired unused, so Arta bought the growth plan.
        $growth = $this->at(95, fn (): EmployerSubscription => $this->ledger->purchasePlan($employer, $this->plans['growth']));

        $p1 = $this->project('p1', $employer, 'web-development', [
            'title' => 'طراحی سایت شرکتی با وردپرس',
            'description' => "سایت شرکتی پنج‌صفحه‌ای با وردپرس: صفحه‌ی اصلی، درباره‌ی ما، خدمات، نمونه‌کارها و تماس.\nقالب آماده‌ی سبک و فارسی ترجیح داده می‌شود و سایت باید روی موبایل عالی دیده شود.",
            'budget_min' => 15_000_000, 'budget_max' => 20_000_000, 'deadline_days' => 60,
            'status' => ProjectStatus::InProgress, 'posting' => PostingType::Subscription, 'subscription' => $growth, 'beginner' => true,
            'skills' => ['wordpress', 'html-css'],
        ], daysAgo: 62);

        $p3 = $this->project('p3', $employer, 'seo', [
            'title' => 'بهینه‌سازی سرعت سایت و سئوی تکنیکال',
            'description' => "سایت فروشگاهی ما کند شده و در گوگل افت کرده است. می‌خواهیم Core Web Vitals بهتر شود، خطاهای سرچ کنسول رفع شود و ساختار لینک‌دهی داخلی اصلاح شود.\nگزارش قبل و بعد از کار لازم است.",
            'budget_min' => 6_000_000, 'budget_max' => 9_000_000, 'deadline_days' => 30,
            'status' => ProjectStatus::Open, 'posting' => PostingType::Subscription, 'subscription' => $growth, 'beginner' => true,
            'skills' => ['technical-seo', 'google-analytics'],
        ], daysAgo: 12);

        $p4 = $this->project('p4', $employer, 'mobile-apps', [
            'title' => 'اپلیکیشن فلاتر برای رزرو نوبت آرایشگاه',
            'description' => "اپ اندروید و iOS با فلاتر برای رزرو نوبت: انتخاب خدمت، انتخاب آرایشگر، تقویم نوبت‌ها و یادآور پیامکی.\nAPI آماده است و فقط سمت اپ لازم است.",
            'budget_min' => 35_000_000, 'budget_max' => 55_000_000, 'deadline_days' => 75,
            'status' => ProjectStatus::Open, 'posting' => PostingType::Subscription, 'subscription' => $growth, 'beginner' => false,
            'skills' => ['flutter', 'android'],
        ], daysAgo: 6);

        $this->project('p5', $employer, 'translation', [
            'title' => 'ترجمه‌ی کاتالوگ محصولات از انگلیسی به فارسی',
            'description' => 'کاتالوگ ۴۰ صفحه‌ای محصولات صنعتی که باید روان و دقیق به فارسی ترجمه شود. اصطلاحات فنی فهرست شده‌اند و در اختیار مترجم قرار می‌گیرند.',
            'budget_min' => 3_000_000, 'budget_max' => 4_500_000, 'deadline_days' => 20,
            'status' => ProjectStatus::PendingReview, 'posting' => PostingType::Subscription, 'subscription' => $growth, 'beginner' => true,
            'skills' => ['en-fa-translation'],
        ], daysAgo: 1);

        $this->project('p6', $employer, 'graphic-design', [
            'title' => 'طراحی لوگو و هویت بصری',
            'description' => 'برای محصول جدیدمان لوگو و راهنمای رنگ و فونت می‌خواهیم. سلیقه‌ی ما ساده و مینیمال است.',
            'budget_min' => 4_000_000, 'budget_max' => 7_000_000, 'deadline_days' => 25,
            'status' => ProjectStatus::Draft, 'posting' => PostingType::Subscription, 'subscription' => $growth, 'beginner' => true,
            'review_note' => 'لطفاً خروجی‌های نهایی (فرمت فایل‌ها و تعداد طرح اولیه) و مخاطب محصول را مشخص کنید تا پروژه منتشر شود.',
            'skills' => ['logo-design', 'illustrator'],
        ], daysAgo: 3);
        $growth->forceFill(['projects_used' => 5])->save();

        // --- shop (Niloofar): first project running, second free posting open ---------------------
        $p7 = $this->project('p7', $shop, 'content-writing', [
            'title' => 'تولید ۲۰ مقاله‌ی سئوشده برای وبلاگ فروشگاه',
            'description' => "بیست مقاله‌ی ۱۲۰۰ کلمه‌ای درباره‌ی نگهداری لوازم خانگی، با رعایت اصول سئو و لحن صمیمی.\nکلمات کلیدی هر مقاله آماده است.",
            'budget_min' => 7_000_000, 'budget_max' => 9_000_000, 'deadline_days' => 40,
            'status' => ProjectStatus::InProgress, 'posting' => PostingType::FreeFirst, 'beginner' => true,
            'skills' => ['seo-writing', 'copywriting'],
        ], daysAgo: 52);
        $this->freePostingUsed($shop, 1, 52);

        $p8 = $this->project('p8', $shop, 'social-media', [
            'title' => 'مدیریت صفحه‌ی اینستاگرام فروشگاه برای یک ماه',
            'description' => 'تولید ۱۲ پست و ۲۰ استوری، پاسخ به دایرکت‌ها در ساعت کاری و گزارش هفتگی رشد صفحه.',
            'budget_min' => 5_000_000, 'budget_max' => 7_000_000, 'deadline_days' => 35,
            'status' => ProjectStatus::Open, 'posting' => PostingType::FreeSecond, 'beginner' => true,
            'skills' => ['instagram-marketing'],
        ], daysAgo: 25);
        $this->freePostingUsed($shop, 2, 25);

        $this->project('p9', $shop, 'graphic-design', [
            'title' => 'طراحی بنرهای تخفیف پاییزه',
            'description' => 'شش بنر برای سایت و اینستاگرام با موضوع جشنواره‌ی تخفیف پاییزه.',
            'budget_min' => 1_500_000, 'budget_max' => 2_500_000, 'deadline_days' => 15,
            'status' => ProjectStatus::Draft, 'posting' => PostingType::FreeFirst, 'beginner' => true,
            'skills' => ['photoshop'],
        ], daysAgo: 2, submitted: false);

        // --- startup (Rahnama): design contract in dispute, React landing page open, starter plan ---
        $p10 = $this->project('p10', $startup, 'ui-ux', [
            'title' => 'طراحی رابط کاربری اپ رهنما در فیگما',
            'description' => 'طراحی ۲۵ صفحه‌ی اپ موبایل رهنما (جستجوی تور، جزئیات، پرداخت و پروفایل) به همراه دیزاین سیستم و پروتوتایپ قابل کلیک.',
            'budget_min' => 20_000_000, 'budget_max' => 25_000_000, 'deadline_days' => 50,
            'status' => ProjectStatus::InProgress, 'posting' => PostingType::FreeFirst, 'beginner' => false,
            'skills' => ['figma', 'user-research'],
        ], daysAgo: 88);
        $this->freePostingUsed($startup, 1, 88);

        $starter = $this->at(30, fn (): EmployerSubscription => $this->ledger->purchasePlan($startup, $this->plans['starter']));

        $p11 = $this->project('p11', $startup, 'web-development', [
            'title' => 'صفحه‌ی فرود محصول با React',
            'description' => "یک لندینگ پیج واکنش‌گرا با React برای معرفی اپ رهنما: بخش ویژگی‌ها، نظرات کاربران، سؤالات متداول و فرم ثبت ایمیل.\nطرح فیگما آماده است.",
            'budget_min' => 8_000_000, 'budget_max' => 12_000_000, 'deadline_days' => 21,
            'status' => ProjectStatus::Open, 'posting' => PostingType::Subscription, 'subscription' => $starter, 'beginner' => true,
            'skills' => ['react', 'javascript', 'html-css'],
        ], daysAgo: 8);

        $this->project('p14', $startup, 'seo', [
            'title' => 'راه‌اندازی گوگل آنالیتیکس و گزارش ماهانه',
            'description' => 'نصب و پیکربندی گوگل آنالیتیکس ۴ و سرچ کنسول، تعریف رویدادهای مهم و یک داشبورد ساده برای گزارش ماهانه.',
            'budget_min' => 2_500_000, 'budget_max' => 4_000_000, 'deadline_days' => 14,
            'status' => ProjectStatus::PendingReview, 'posting' => PostingType::Subscription, 'subscription' => $starter, 'beginner' => true,
            'skills' => ['google-analytics'],
        ], daysAgo: 0);
        $starter->forceFill(['projects_used' => 2])->save();

        // --- agency (Pixel): cancelled first project, free window over, no plan ----------------------
        $this->project('p12', $agency, 'content-writing', [
            'title' => 'بازنویسی متن‌های سایت آژانس',
            'description' => 'متن صفحه‌های اصلی سایت آژانس باید کوتاه‌تر، گیراتر و متناسب با مخاطب کسب‌وکارهای کوچک بازنویسی شود.',
            'budget_min' => 2_000_000, 'budget_max' => 3_000_000, 'deadline_days' => 20,
            'status' => ProjectStatus::Cancelled, 'posting' => PostingType::FreeFirst, 'beginner' => true,
            'skills' => ['copywriting'],
        ], daysAgo: 180);
        $this->freePostingUsed($agency, 1, 180);

        $this->project('p13', $agency, 'translation', [
            'title' => 'ترجمه‌ی مقالات تخصصی بازاریابی',
            'description' => 'ترجمه‌ی ماهانه‌ی ده مقاله‌ی تخصصی بازاریابی دیجیتال از منابع انگلیسی، با حفظ اصطلاحات رایج فارسی.',
            'budget_min' => 4_000_000, 'budget_max' => 6_000_000, 'deadline_days' => 30,
            'status' => ProjectStatus::Draft, 'posting' => PostingType::FreeFirst, 'beginner' => true,
            'skills' => ['en-fa-translation'],
        ], daysAgo: 5, submitted: false);

        // --- proposals ---------------------------------------------------------------------------------
        $hired2 = $this->proposal($p2, 'hossein', 44_000_000, 40, ProposalStatus::Pending, 'سلام، من چهار سال است با لاراول پنل فروشگاهی می‌سازم. برای این پروژه ماژول محصولات، سفارش‌ها، گزارش‌ها و نقش‌ها را پیاده می‌کنم و هر هفته نسخه‌ی قابل تست تحویل می‌دهم.', 147);

        $hired1 = $this->proposal($p1, 'freelancer', 17_500_000, 30, ProposalStatus::Pending, 'سلام، سایت شرکتی را با یک قالب سبک فارسی می‌سازم، سرعت و نمایش موبایل را بهینه می‌کنم و بعد از تحویل، کار با پنل وردپرس را به تیمتان آموزش می‌دهم.', 58, feedbackBy: 'mentor', feedback: 'پیشنهاد خوبی است. پیشنهاد می‌کنم زمان هر مرحله را هم بنویسی تا کارفرما با خیال راحت تصمیم بگیرد.', mentor: true);
        $this->proposal($p1, 'zahra', 19_000_000, 25, ProposalStatus::Pending, 'سلام، طراحی رابط را هم خودم انجام می‌دهم تا سایت یکدست و حرفه‌ای شود. نمونه‌کارهای مرتبط در پروفایلم هست.', 57);

        $hired7 = $this->proposal($p7, 'fatemeh', 8_000_000, 35, ProposalStatus::Pending, 'سلام، مقاله‌ها را بعد از تحقیق کلمات کلیدی و با ساختار تیتربندی استاندارد می‌نویسم و هر هفته پنج مقاله تحویل می‌دهم.', 49, feedbackBy: 'mentor2', feedback: 'لحن محترمانه و برنامه‌ی تحویل روشنی داری. یک نمونه‌ی کوتاه ضمیمه کن تا اعتماد کارفرما بیشتر شود.', mentor: true);

        $hired10 = $this->proposal($p10, 'zahra', 22_000_000, 45, ProposalStatus::Pending, 'سلام، دیزاین سیستم و ۲۵ صفحه را در سه مرحله تحویل می‌دهم و در پایان پروتوتایپ کامل در فیگما آماده است.', 85, mentor: true);

        $this->proposal($p3, 'freelancer', 7_000_000, 20, ProposalStatus::Shortlisted, 'سلام، برای بهبود سرعت، تصاویر و کش را بهینه می‌کنم، اسکریپت‌های اضافه را حذف می‌کنم و خطاهای سرچ کنسول را با گزارش قبل و بعد رفع می‌کنم.', 10, feedbackBy: 'mentor', feedback: 'عالی! فقط ابزارهایی که برای اندازه‌گیری استفاده می‌کنی (PageSpeed و Search Console) را هم نام ببر.');
        $this->proposal($p3, 'ali', 6_000_000, 25, ProposalStatus::Pending, 'سلام، در حال یادگیری سئوی تکنیکال هستم و پروژه را مرحله‌به‌مرحله و با گزارش منظم جلو می‌برم.', 9, mentor: true);
        $this->proposal($p3, 'narges', 8_500_000, 21, ProposalStatus::Pending, 'سلام، علاوه بر سئوی تکنیکال، برای شبکه‌های اجتماعی هم برنامه‌ی محتوا پیشنهاد می‌دهم.', 7);

        $this->proposal($p4, 'hossein', 50_000_000, 70, ProposalStatus::Pending, 'سلام، دو اپ فلاتر مشابه تحویل داده‌ام؛ معماری تمیز، تست و انتشار در استورها را هم انجام می‌دهم.', 4);

        $this->proposal($p8, 'narges', 6_000_000, 30, ProposalStatus::Pending, 'سلام، سه صفحه‌ی فروشگاهی را مدیریت کرده‌ام و تقویم محتوای ماهانه را قبل از شروع برایتان می‌فرستم.', 12);
        $this->proposal($p8, 'fatemeh', 5_500_000, 30, ProposalStatus::Rejected, 'سلام، کپشن‌ها و متن استوری‌ها را با لحن برند شما می‌نویسم.', 11);

        $this->proposal($p11, 'freelancer', 9_500_000, 18, ProposalStatus::Pending, 'سلام، لندینگ را با React و کامپوننت‌های قابل استفاده‌ی دوباره می‌سازم، در موبایل کاملاً واکنش‌گراست و فرم ایمیل را به سرویس شما وصل می‌کنم.', 6, feedbackBy: 'mentor', feedback: 'خوب نوشتی. زمان ۱۸ روز کمی فشرده است؛ اگر مطمئنی مشکلی نیست، در غیر این صورت ۲۱ روز بگذار.');
        $this->proposal($p11, 'ali', 8_000_000, 21, ProposalStatus::Pending, 'سلام، از طرح فیگما کامپوننت‌ها را دقیق پیاده می‌کنم و کد را تمیز و مستند تحویل می‌دهم.', 5, mentor: true);
        $this->proposal($p11, 'hossein', 12_000_000, 14, ProposalStatus::Withdrawn, 'سلام، با تجربه‌ی لندینگ‌های مشابه، کار را سریع و باکیفیت تحویل می‌دهم.', 7);

        // --- contracts, milestones and escrow --------------------------------------------------------------
        // Arta × Hossein: completed, all paid, reviewed (fee 20%).
        $c2 = $this->contracts['c2'] = $this->hire($hired2, daysAgo: 145);
        $this->milestone($c2, 'پیاده‌سازی مدیریت محصولات و موجودی', 15_000_000, fundedAgo: 144, submittedAgo: 126, releasedAgo: 120);
        $this->milestone($c2, 'سفارش‌ها و گزارش فروش', 17_000_000, fundedAgo: 119, submittedAgo: 104, releasedAgo: 100);
        $this->milestone($c2, 'نقش‌ها، تست نهایی و استقرار', 12_000_000, fundedAgo: 99, submittedAgo: 88, releasedAgo: 85);
        $this->closeContract($c2, ContractStatus::Completed, 84);
        $this->at(83, fn () => Review::create([
            'contract_id' => $c2->id,
            'reviewer_id' => $employer->id,
            'reviewee_id' => $this->users['hossein']->id,
            'rating' => 5,
            'comment' => 'کد تمیز، ارتباط عالی و تحویل به‌موقع. حتماً دوباره با حسین کار می‌کنیم.',
        ]));

        // Arta × Mohammad (beginner, asked for a mentor: free): one step paid, one delivered, one planned.
        $c1 = $this->contracts['c1'] = $this->hire($hired1, daysAgo: 55);
        $this->milestone($c1, 'طراحی قالب و صفحه‌ی اصلی', 6_000_000, fundedAgo: 54, submittedAgo: 42, releasedAgo: 40);
        $this->milestone($c1, 'صفحه‌های داخلی و فرم تماس', 7_000_000, fundedAgo: 39, submittedAgo: 2);
        $this->milestone($c1, 'انتشار، سئوی پایه و آموزش پنل', 4_500_000);

        // Niloofar × Fatemeh (beginner): first half paid, second half in escrow.
        $c3 = $this->contracts['c3'] = $this->hire($hired7, daysAgo: 46);
        $this->milestone($c3, 'ده مقاله‌ی اول', 4_000_000, fundedAgo: 45, submittedAgo: 27, releasedAgo: 25);
        $this->milestone($c3, 'ده مقاله‌ی دوم', 4_000_000, fundedAgo: 24);

        // Rahnama × Zahra (junior, asked for a mentor: fee 25%): first step in escrow, then a dispute.
        $c4 = $this->contracts['c4'] = $this->hire($hired10, daysAgo: 82);
        $this->milestone($c4, 'دیزاین سیستم و ۱۰ صفحه‌ی اول', 10_000_000, fundedAgo: 81);
        $this->milestone($c4, '۱۵ صفحه‌ی باقی‌مانده و پروتوتایپ', 12_000_000);
        $this->at(6, function () use ($c4): void {
            Dispute::create([
                'contract_id' => $c4->id,
                'raised_by' => $this->users['zahra']->id,
                'reason' => 'بعد از تحویل نسخه‌ی اول، کارفرما هشت صفحه‌ی جدید به دامنه‌ی کار اضافه کرده ولی حاضر نیست مبلغ یا زمان را تغییر دهد. مرحله‌ی اول هم هنوز تأیید نشده است.',
                'status' => DisputeStatus::Open,
            ]);
            $c4->update(['status' => ContractStatus::Disputed]);
        });
        $this->at(20, fn () => Dispute::create([
            'contract_id' => $c2->id,
            'raised_by' => $employer->id,
            'reason' => 'تحویل مرحله‌ی دوم یک هفته دیرتر از زمان توافق‌شده انجام شد.',
            'status' => DisputeStatus::Resolved,
            'outcome' => DisputeOutcome::Continue,
            'resolved_by' => $this->users['admin']->id,
            'resolution_note' => 'با توافق دو طرف، تأخیر به دلیل تغییر درخواست کارفرما بود و پرونده بسته شد.',
            'resolved_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function project(string $key, User $employer, string $category, array $data, int $daysAgo, bool $submitted = true): Project
    {
        return $this->projects[$key] = $this->at($daysAgo, function () use ($employer, $category, $data, $submitted): Project {
            $status = $data['status'];
            $live = in_array($status, [ProjectStatus::Open, ProjectStatus::InProgress, ProjectStatus::Completed, ProjectStatus::Cancelled], true);

            $project = $employer->postedProjects()->create([
                'category_id' => $this->categories[$category]->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'budget_type' => $data['budget_type'] ?? BudgetType::Fixed,
                'budget_min' => $data['budget_min'],
                'budget_max' => $data['budget_max'],
                'status' => $status,
                'is_beginner_friendly' => $data['beginner'],
                'deadline' => now()->addDays($data['deadline_days'])->toDateString(),
                'published_at' => $live ? now()->addHours(5) : null,
                'posting_type' => $data['posting'],
                'subscription_id' => isset($data['subscription']) ? $data['subscription']->id : null,
            ]);

            $project->forceFill([
                'submitted_at' => $submitted ? now()->addHour() : null,
                'review_note' => $data['review_note'] ?? null,
            ])->save();

            $project->skills()->sync(array_map(fn (string $slug): int => $this->skills[$slug]->id, $data['skills']));

            return $project;
        });
    }

    private function freePostingUsed(User $employer, int $used, int $daysAgo): void
    {
        $profile = $employer->employerProfile()->first();

        $profile->forceFill($used === 1
            ? [
                'free_projects_used' => 1,
                'first_free_project_at' => $this->now->subDays($daysAgo),
                'second_free_until' => $this->now->subDays($daysAgo)->addDays(30),
            ]
            : ['free_projects_used' => 2])->save();
    }

    private function proposal(Project $project, string $freelancer, int $price, int $days, ProposalStatus $status, string $letter, int $daysAgo, ?string $feedbackBy = null, ?string $feedback = null, bool $mentor = false): Proposal
    {
        return $this->at($daysAgo, function () use ($project, $freelancer, $price, $days, $status, $letter, $feedbackBy, $feedback, $mentor): Proposal {
            $proposal = $project->proposals()->make([
                'cover_letter' => $letter,
                'proposed_price' => $price,
                'delivery_days' => $days,
                'mentorship_requested' => $mentor,
                'status' => $status,
                'mentor_reviewed_by' => $feedbackBy ? $this->users[$feedbackBy]->id : null,
                'mentor_feedback' => $feedback,
            ]);
            $proposal->freelancer()->associate($this->users[$freelancer]);
            $proposal->save();

            return $proposal;
        }, hour: 11);
    }

    /**
     * Hire the proposal like Employer\ContractController does: the good-faith deposit is held, and
     * when the freelancer asked for a mentor a ticket joins the mentors' queue (beginners' first
     * mentorships are free).
     */
    private function hire(Proposal $proposal, int $daysAgo): Contract
    {
        return $this->at($daysAgo, function () use ($proposal): Contract {
            $project = Project::findOrFail($proposal->project_id);
            $freelancer = User::with('freelancerProfile')->findOrFail($proposal->freelancer_id);
            $profile = $freelancer->freelancerProfile;
            $mentorship = $proposal->mentorship_requested;
            $free = $mentorship && $profile->level === ExperienceLevel::Beginner && $profile->free_mentorships_used < 2;

            if ($free) {
                $profile->increment('free_mentorships_used');
            }

            $contract = Contract::create([
                'project_id' => $project->id,
                'proposal_id' => $proposal->id,
                'employer_id' => $project->employer_id,
                'freelancer_id' => $freelancer->id,
                'amount' => $proposal->proposed_price,
                'mentorship_included' => $mentorship,
                'is_free_mentorship' => $free,
                'fee_percent' => $mentorship && ! $free ? 25 : 20,
                'mentor_share_percent' => $mentorship ? 3.5 : 0,
                'status' => ContractStatus::Active,
                'started_at' => now(),
            ]);

            $contract->setRelation('project', $project);
            $this->ledger->holdHireDeposit($contract, 45);

            if ($mentorship) {
                $this->contractTickets[$contract->id] = $freelancer->tickets()->create([
                    'contract_id' => $contract->id,
                    'ticket_type' => TicketType::Technical,
                    'channel' => TicketChannel::Ticket,
                    'subject' => 'منتورینگ پروژه: '.$project->title,
                    'message' => 'فریلنسر در پیشنهادش برای این پروژه منتور خواسته و استخدام شده است. مبلغ قرارداد: '.PersianText::number($contract->amount).' تومان، زمان تحویل: '.PersianText::number($proposal->delivery_days).' روز.',
                    'status' => TicketStatus::Open,
                ]);
            }

            $proposal->update(['status' => ProposalStatus::Accepted]);

            return $contract;
        }, hour: 15);
    }

    /**
     * A milestone that is planned now and optionally funded, delivered and released at later dates.
     */
    private function milestone(Contract $contract, string $title, int $amount, ?int $fundedAgo = null, ?int $submittedAgo = null, ?int $releasedAgo = null): Milestone
    {
        $milestone = $this->at($fundedAgo ?? 1, fn (): Milestone => $contract->milestones()->create([
            'title' => $title,
            'description' => null,
            'amount' => $amount,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => MilestoneStatus::Pending,
            'sort_order' => $contract->milestones()->count() + 1,
        ]), hour: 12);

        if ($fundedAgo !== null) {
            $this->at($fundedAgo, fn () => $this->ledger->fundMilestone($milestone), hour: 13);
        }

        if ($submittedAgo !== null) {
            $this->at($submittedAgo, fn () => $milestone->update(['status' => MilestoneStatus::Submitted]), hour: 17);
        }

        if ($releasedAgo !== null) {
            $this->at($releasedAgo, fn () => $this->ledger->releaseMilestone($milestone), hour: 10);
        }

        return $milestone;
    }

    private function closeContract(Contract $contract, ContractStatus $status, int $daysAgo): void
    {
        $this->at($daysAgo, function () use ($contract, $status): void {
            if ($status === ContractStatus::Completed) {
                $this->ledger->refundDeposit($contract);
            }

            $contract->update([
                'status' => $status,
                'completed_at' => $status === ContractStatus::Completed ? now() : null,
            ]);
        });
    }

    // ---------------------------------------------------------------- payouts

    /**
     * Payout cards and "pay my wallet to my card" requests: paid (with tracking number and receipt), pending and rejected.
     */
    private function withdrawals(): void
    {
        $finance = $this->users['finance'];
        $support = $this->users['support'];

        $hossein = $this->bankCard('hossein', '6104337912345672', 90);
        $request = $this->at(80, fn () => $this->ledger->requestWithdrawal($this->users['hossein'], $hossein, 20_000_000), hour: 9);
        $this->at(79, function () use ($request, $finance): void {
            $path = 'withdrawal-receipts/demo-'.$request->id.'.png';
            Storage::disk('local')->put($path, $this->mockupImage(2));
            $this->ledger->markWithdrawalPaid($request, $finance, '140306281234', $path);
        }, hour: 12);

        $mohammad = $this->bankCard('freelancer', '6037991234567893', 30);
        $this->at(2, fn () => $this->ledger->requestWithdrawal($this->users['freelancer'], $mohammad, 3_000_000), hour: 18);

        $fatemeh = $this->bankCard('fatemeh', '6219861234567891', 20);
        $rejected = $this->at(15, fn () => $this->ledger->requestWithdrawal($this->users['fatemeh'], $fatemeh, 1_000_000), hour: 20);
        $this->at(14, fn () => $this->ledger->cancelWithdrawal($rejected, WithdrawalStatus::Rejected, $support, 'شماره‌ی شبای ثبت‌شده در بانک با این کارت یکی نیست؛ لطفاً کارت را دوباره بررسی و ثبت کن.'), hour: 11);
    }

    private function bankCard(string $username, string $number, int $daysAgo): BankCard
    {
        $number = CardNumber::isValid($number) ? $number : self::luhnFix($number);
        $user = $this->users[$username];

        return $this->at($daysAgo, fn (): BankCard => $user->bankCards()->create([
            'card_number' => $number,
            'holder_name' => $user->name,
            'phone' => $user->phone,
            'bank_name' => CardNumber::bankName($number),
        ]), hour: 10);
    }

    /**
     * Replace the last digit so the demo number passes the Luhn check.
     */
    private static function luhnFix(string $number): string
    {
        foreach (range(0, 9) as $digit) {
            $candidate = substr($number, 0, 15).$digit;

            if (CardNumber::isValid($candidate)) {
                return $candidate;
            }
        }

        return $number;
    }

    // ---------------------------------------------------------------- mentoring

    private function mentoring(): void
    {
        $mentor = $this->users['mentor'];
        $mentor2 = $this->users['mentor2'];

        // Requests still waiting in the shared queue.
        $this->ticket('ali', TicketType::Technical, 'برای اولین پیشنهادم چه قیمتی بدهم؟', 'پروژه‌ی لندینگ React را دیده‌ام ولی نمی‌دانم قیمت پیشنهادی‌ام منطقی است یا نه. تجربه‌ی کار با مشتری ندارم.', TicketStatus::Open, null, 1);
        $this->ticket('narges', TicketType::Motivational, 'حسابم معلق شد و ناامید شده‌ام', 'نمی‌دانستم نباید شماره‌ام را در چت بفرستم. می‌خواهم بدانم چطور دوباره اعتماد کارفرماها را جلب کنم.', TicketStatus::Open, null, 0, TicketChannel::Phone, '09120000306');
        $this->ticket('ali', TicketType::Technical, 'مرحله‌بندی اولین قرارداد', 'اگر استخدام شوم، کار را چطور به مرحله‌های منصفانه تقسیم کنم که کارفرما هم راضی باشد؟', TicketStatus::Open, null, 2);

        // Taken by mentors.
        $this->ticket('hossein', TicketType::Technical, 'مسیر رسیدن به سطح ارشد', 'می‌خواهم معماری نرم‌افزار و مدیریت تیم را جدی‌تر یاد بگیرم. از کجا شروع کنم؟', TicketStatus::Assigned, $mentor, 3);

        $this->ticket('freelancer', TicketType::Technical, 'خطای ۴۱۹ در فرم تماس لاراول', 'فرم تماس در سایت تمرینی‌ام بعد از چند دقیقه خطای 419 می‌دهد. توکن CSRF را گذاشته‌ام ولی باز هم خطا می‌گیرم.', TicketStatus::Assigned, $mentor, 4);
        $t3 = $this->ticket('zahra', TicketType::Technical, 'بهترین روش تحویل فایل‌های فیگما', 'فایل فیگما را چطور مرتب کنم که برنامه‌نویس راحت پیاده‌سازی کند؟', TicketStatus::Closed, $mentor, 120);

        // Tickets opened by hires (the freelancer asked for a mentor on the proposal).
        $t1 = $this->takeContractTicket('c1', $mentor, TicketStatus::InProgress, 53);
        $t2 = $this->takeContractTicket('c3', $mentor2, TicketStatus::InProgress, 45);
        $this->takeContractTicket('c4', $mentor, TicketStatus::Assigned, 80);

        // Programs created from those tickets, with their sessions (a hire's program becomes the contract's mentor).
        $program1 = $this->program($t1, $mentor, 'freelancer', 'همراهی در قرارداد سایت شرکتی آرتا: رفع خطاهای رایج و تحویل موفق مرحله‌ها.', MentorshipProgramStatus::Active, free: true, daysAgo: 23);
        $this->session($program1, -20, MentorshipSessionType::Technical, MentorshipSessionStatus::Done, 5, 'علت خطای ۴۱۹ منقضی شدن نشست بود؛ مدیریت session و کش را مرور کردیم.');
        $this->session($program1, -9, MentorshipSessionType::Review, MentorshipSessionStatus::Done, 4, 'کد صفحه‌های داخلی را بازبینی کردیم؛ دو پیشنهاد برای ساختار قالب دادم.');
        $this->session($program1, -2, MentorshipSessionType::Motivational, MentorshipSessionStatus::Missed, null, 'محمد به جلسه نرسید؛ جلسه‌ی بعد را هماهنگ کردیم.');
        $this->session($program1, 2, MentorshipSessionType::Technical, MentorshipSessionStatus::Scheduled, null, null, 'https://meet.jit.si/jumplancer-mentor-1');
        $this->session($program1, 9, MentorshipSessionType::Review, MentorshipSessionStatus::Scheduled, null, null, 'https://meet.jit.si/jumplancer-mentor-1');

        $program2 = $this->program($t2, $mentor2, 'fatemeh', 'تحویل مطمئن ده مقاله‌ی اول و ساختن اعتمادبه‌نفس در ارتباط با کارفرما.', MentorshipProgramStatus::Active, free: true, daysAgo: 43);
        $this->session($program2, -40, MentorshipSessionType::Motivational, MentorshipSessionStatus::Done, 5, 'برنامه‌ی نوشتن روزانه را با هم چیدیم.');
        $this->session($program2, -26, MentorshipSessionType::Review, MentorshipSessionStatus::Done, 5, 'پنج مقاله را بازبینی کردیم؛ تیترها عالی شده‌اند.');
        $this->session($program2, -12, MentorshipSessionType::Technical, MentorshipSessionStatus::Done, 4, 'اصول لینک‌سازی داخلی را تمرین کردیم.');
        $this->session($program2, 1, MentorshipSessionType::Motivational, MentorshipSessionStatus::Scheduled, null, null, 'https://meet.jit.si/jumplancer-mentor-2');

        $program3 = $this->program($t3, $mentor, 'zahra', 'نظم‌دهی فایل‌های فیگما برای تحویل به تیم فنی.', MentorshipProgramStatus::Completed, free: false, daysAgo: 119, price: 900_000);
        $this->session($program3, -66, MentorshipSessionType::Technical, MentorshipSessionStatus::Done, 5, 'نام‌گذاری لایه‌ها و کامپوننت‌ها را مرتب کردیم.');
        $this->session($program3, -59, MentorshipSessionType::Review, MentorshipSessionStatus::Done, 5, 'فایل نهایی آماده‌ی تحویل بود.');
        $program3->update(['ended_at' => $this->now->subDays(58)]);
        $t3->update(['closed_at' => $this->now->subDays(58)]);
    }

    private function ticket(string $requester, TicketType $type, string $subject, string $message, TicketStatus $status, ?User $mentor, int $daysAgo, TicketChannel $channel = TicketChannel::Ticket, ?string $phone = null): Ticket
    {
        return $this->at($daysAgo, fn (): Ticket => $this->users[$requester]->tickets()->create([
            'ticket_type' => $type,
            'channel' => $channel,
            'phone_number' => $phone,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'assigned_mentor_id' => $mentor?->id,
            'closed_at' => $status === TicketStatus::Closed ? now()->addDays(10) : null,
        ]), hour: 14);
    }

    /**
     * A mentor takes the ticket that a hire opened.
     */
    private function takeContractTicket(string $contract, User $mentor, TicketStatus $status, int $daysAgo): Ticket
    {
        $ticket = $this->contractTickets[$this->contracts[$contract]->id];

        $this->at($daysAgo, fn () => $ticket->update(['assigned_mentor_id' => $mentor->id, 'status' => $status]), hour: 11);

        return $ticket;
    }

    private function program(Ticket $ticket, User $mentor, string $mentee, string $goal, MentorshipProgramStatus $status, bool $free, int $daysAgo, ?int $price = null): MentorshipProgram
    {
        // A hire's free mentorship was already counted when the contract was made.
        if ($free && $ticket->contract_id === null) {
            $this->users[$mentee]->freelancerProfile()->first()->increment('free_mentorships_used');
        }

        if ($ticket->contract_id !== null) {
            Contract::whereKey($ticket->contract_id)->update(['mentor_id' => $mentor->id]);
        }

        return $this->at($daysAgo, fn (): MentorshipProgram => MentorshipProgram::create([
            'ticket_id' => $ticket->id,
            'mentor_id' => $mentor->id,
            'mentee_id' => $this->users[$mentee]->id,
            'track' => MentorshipTrack::Freelancer,
            'goal' => $goal,
            'status' => $status,
            'is_free_mentorship' => $free,
            'price' => $free || $ticket->contract_id !== null ? null : $price,
            'started_at' => now(),
        ]), hour: 16);
    }

    /**
     * $inDays is relative to today: negative for past sessions, positive for upcoming ones.
     */
    private function session(MentorshipProgram $program, int $inDays, MentorshipSessionType $type, MentorshipSessionStatus $status, ?int $rating, ?string $notes, ?string $link = null): void
    {
        // 18:30 on the users' clock (Tehran); stored in UTC like every timestamp.
        $scheduledAt = $this->now->setTimezone(self::LOCAL_TIMEZONE)->addDays($inDays)->setTime(18, 30)->utc();

        $this->at(max(0, -$inDays + 3), fn () => $program->sessions()->create([
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $type === MentorshipSessionType::Review ? 60 : 45,
            'session_type' => $type,
            'status' => $status,
            'meeting_link' => $link,
            'mentor_notes' => $notes,
            'mentee_rating' => $rating,
        ]));
    }

    // ---------------------------------------------------------------- learning content

    private function learningContent(): void
    {
        $items = [
            ['mentor', 'نقشه‌ی راه یادگیری لاراول برای تازه‌کارها', LearningContentType::Roadmap, ContentAudience::Freelancer, ContentPurpose::Technical, 'web-development', "۱. مبانی PHP و شی‌گرایی\n۲. مسیرها، کنترلرها و Blade\n۳. Eloquent و مهاجرت‌ها\n۴. احراز هویت و سطح دسترسی\n۵. تست‌نویسی با PHPUnit\n۶. ساخت یک پروژه‌ی کامل و انتشار آن", null, true, 60],
            ['mentor2', 'چک‌لیست قبل از ارسال پیشنهاد', LearningContentType::Checklist, ContentAudience::Freelancer, ContentPurpose::Technical, null, "شرح پروژه را کامل خواندم\nنیاز کارفرما را با کلمات خودم خلاصه کردم\nخروجی نهایی را دقیق نوشتم\nقیمت و زمان واقع‌بینانه دادم\nیک نمونه‌کار مرتبط معرفی کردم\nاطلاعات تماس در متن نیست", null, true, 45],
            ['mentor2', 'چطور با اولین کارفرما صحبت کنیم', LearningContentType::Article, ContentAudience::Freelancer, ContentPurpose::Motivational, null, 'اولین گفت‌وگو با کارفرما همیشه کمی استرس دارد. سؤال‌هایت را از قبل بنویس، درباره‌ی خروجی نهایی توافق کن و هر تغییر را در همین پلتفرم ثبت کن تا هر دو طرف با خیال راحت کار کنید.', null, true, 30],
            ['admin', 'تعریف مرحله‌های قرارداد برای کارفرماها', LearningContentType::Article, ContentAudience::Employer, ContentPurpose::Technical, null, 'کار را به مرحله‌های کوچک و قابل تحویل تقسیم کنید. مبلغ هر مرحله قبل از شروع در امانت قرار می‌گیرد و فقط بعد از تأیید شما به فریلنسر پرداخت می‌شود.', null, true, 20],
            ['admin', 'آشنایی با امانت و پرداخت مرحله‌ای', LearningContentType::Video, ContentAudience::All, ContentPurpose::Technical, null, 'در این ویدیوی کوتاه می‌بینید پول شما در هر مرحله کجاست و چطور آزاد می‌شود.', 'https://www.aparat.com/v/jumplancer-escrow', true, 12],
            ['mentor', 'الگوی نوشتن شرح پروژه', LearningContentType::Checklist, ContentAudience::Employer, ContentPurpose::Technical, null, "هدف پروژه\nخروجی‌های نهایی\nامکانات ضروری و اختیاری\nفایل‌ها و دسترسی‌های آماده\nمهلت و بودجه", null, false, 2],
        ];

        foreach ($items as [$author, $title, $type, $audience, $purpose, $category, $body, $url, $published, $daysAgo]) {
            $this->at($daysAgo, fn () => $this->users[$author]->learningContents()->create([
                'category_id' => $category ? $this->categories[$category]->id : null,
                'title' => $title,
                'content_type' => $type,
                'audience' => $audience,
                'purpose' => $purpose,
                'body' => $body,
                'media_url' => $url,
                'is_published' => $published,
                'published_at' => $published ? now() : null,
            ]));
        }
    }

    // ---------------------------------------------------------------- portfolio

    private function portfolio(): void
    {
        $admin = $this->users['admin'];

        $items = [
            ['freelancer', 'web-development', 'سایت فروشگاهی تمرینی با ووکامرس', 'توسعه‌دهنده‌ی وردپرس', 'یک فروشگاه کامل با ووکامرس برای دوره‌ی آموزشی ساختم: قالب فارسی، درگاه آزمایشی و بهینه‌سازی برای موبایل.', 'امتیاز PageSpeed موبایل از ۴۲ به ۸۵ رسید.', 21, ['wordpress', 'html-css'], [
                ['shop-home.png', ModerationStatus::Approved],
                ['checkout.png', ModerationStatus::PendingReview],
            ], 20],
            ['freelancer', 'web-development', 'سیستم نوبت‌دهی مطب با لاراول', 'توسعه‌دهنده‌ی بک‌اند', 'پروژه‌ی پایانی دوره‌ی لاراول: رزرو نوبت، پنل پزشک و پیامک یادآوری.', null, 30, ['laravel', 'php', 'mysql'], [
                ['clinic-report.pdf', ModerationStatus::Approved],
            ], 12],
            ['hossein', 'web-development', 'پنل مدیریت فروشگاه آرتا', 'توسعه‌دهنده‌ی فول‌استک', 'پنل کامل مدیریت فروشگاه با گزارش‌گیری و نقش‌های کاربری.', 'زمان ثبت سفارش در انبار ۶۰٪ کمتر شد.', 55, ['laravel', 'mysql', 'bootstrap'], [
                ['admin-dashboard.png', ModerationStatus::Approved],
            ], 80],
            ['zahra', 'ui-ux', 'بازطراحی اپ تاکسی‌یاب', 'طراح UI/UX', 'بازطراحی جریان سفارش سفر با تمرکز بر کاهش مراحل.', 'مراحل سفارش از ۷ به ۴ کاهش یافت.', 25, ['figma', 'user-research'], [
                ['ride-flow.png', ModerationStatus::PendingReview],
                ['contact-card.png', ModerationStatus::Rejected],
            ], 3],
        ];

        foreach ($items as [$owner, $category, $title, $role, $description, $outcome, $days, $skills, $files, $daysAgo]) {
            $this->at($daysAgo, function () use ($owner, $category, $title, $role, $description, $outcome, $days, $skills, $files, $admin): void {
                $user = $this->users[$owner];

                /** @var PortfolioItem $item */
                $item = $user->portfolioItems()->create([
                    'category_id' => $this->categories[$category]->id,
                    'title' => $title,
                    'role' => $role,
                    'description' => $description,
                    'outcome' => $outcome,
                    'duration_days' => $days,
                    'is_visible' => true,
                ]);
                $item->skills()->sync(array_map(fn (string $slug): int => $this->skills[$slug]->id, $skills));

                foreach ($files as $index => [$filename, $status]) {
                    $isPdf = str_ends_with($filename, '.pdf');
                    $path = "portfolio/{$user->id}/demo-{$item->id}-{$index}.".($isPdf ? 'pdf' : 'png');
                    Storage::disk('local')->put($path, $isPdf ? $this->demoPdf($title) : $this->mockupImage($index));

                    $media = $item->media()->create([
                        'file_path' => $path,
                        'file_type' => $isPdf ? PortfolioMediaType::Pdf : PortfolioMediaType::Image,
                        'original_filename' => $filename,
                    ]);

                    $media->forceFill([
                        'status' => $status,
                        'reviewed_by' => $status === ModerationStatus::PendingReview ? null : $admin->id,
                        'reviewed_at' => $status === ModerationStatus::PendingReview ? null : now()->addDay(),
                        'rejection_reason' => $status === ModerationStatus::Rejected ? 'در تصویر شماره‌ی تماس دیده می‌شود؛ آن را بپوشانید و دوباره آپلود کنید.' : null,
                    ])->save();
                }
            });
        }
    }

    /**
     * A small website mockup in the brand colors (PNG), drawn with GD when it is available.
     */
    private function mockupImage(int $variant): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode(self::FALLBACK_PNG);
        }

        $width = 640;
        $height = 360;
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate($image, (int) (0 + 10 * $t), (int) (107 - 46 * $t), (int) (130 - 55 * $t));
            imageline($image, 0, $y, $width, $y, $color);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $bar = imagecolorallocate($image, 238, 243, 246);
        $line = imagecolorallocate($image, 214, 223, 229);
        $orange = imagecolorallocate($image, 247, 148, 29);
        $teal = imagecolorallocate($image, 0, 107, 130);

        imagefilledrectangle($image, 60, 40, 580, 330, $white);
        imagefilledrectangle($image, 60, 40, 580, 68, $bar);

        foreach ([0, 1, 2] as $dot) {
            imagefilledellipse($image, 80 + $dot * 18, 54, 10, 10, $dot === 0 ? $orange : $line);
        }

        imagefilledrectangle($image, 84, 90, 556, 170, $variant % 2 === 0 ? $teal : $orange);
        imagefilledrectangle($image, 110, 110, 330, 124, $white);
        imagefilledrectangle($image, 110, 136, 260, 146, $white);

        foreach ([0, 1, 2] as $column) {
            $x = 84 + $column * 162;
            imagefilledrectangle($image, $x, 190, $x + 146, 300, $bar);
            imagefilledrectangle($image, $x + 14, 206, $x + 60, 240, $column === $variant % 3 ? $orange : $line);
            imagefilledrectangle($image, $x + 14, 254, $x + 120, 262, $line);
            imagefilledrectangle($image, $x + 14, 274, $x + 96, 282, $line);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * A one-page PDF placeholder for PDF case-study files.
     */
    private function demoPdf(string $title): string
    {
        $text = 'Jump Lancer demo case study';
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    // ---------------------------------------------------------------- moderation

    private function violations(): void
    {
        $admin = $this->users['admin'];
        $narges = $this->users['narges'];

        // Narges shared her phone number in a pre-hire chat: the message was blocked and her account suspended.
        $this->at(4, function () use ($narges): void {
            $conversation = Conversation::create(['project_id' => $this->projects['p8']->id]);
            $message = $conversation->messages()->create([
                'sender_id' => $narges->id,
                'body' => 'برای هماهنگی سریع‌تر به این شماره پیام بدهید.',
                'status' => MessageStatus::Blocked,
                'blocked_reason' => 'phone',
            ]);
            $message->violations()->create([
                'user_id' => $narges->id,
                'violation_type' => ViolationType::Phone,
                'detected_content' => '0912 *** ** 06',
                'detected_by' => ViolationSource::AutoFilter,
                'action_taken' => ViolationAction::Suspended,
            ]);

            $narges->forceFill([
                'status' => UserStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => 'اشتراک شماره‌ی تماس در گفت‌وگوی پروژه',
            ])->save();
        });

        // Ali sent an email address; a warning was enough and a moderator reviewed it.
        $this->at(8, function () use ($admin): void {
            $conversation = Conversation::create(['project_id' => $this->projects['p3']->id]);
            $message = $conversation->messages()->create([
                'sender_id' => $this->users['ali']->id,
                'body' => 'فایل گزارش را به ایمیلم بفرستید.',
                'status' => MessageStatus::Blocked,
                'blocked_reason' => 'email',
            ]);
            $violation = $message->violations()->create([
                'user_id' => $this->users['ali']->id,
                'violation_type' => ViolationType::Email,
                'detected_content' => 'ali.***@gmail.com',
                'detected_by' => ViolationSource::AutoFilter,
                'action_taken' => ViolationAction::Warning,
            ]);
            $violation->forceFill(['reviewed_by' => $admin->id, 'reviewed_at' => now()->addHours(3)])->save();
        });

        // Hossein pasted an outside link in the contract chat; still waiting for review.
        $this->at(1, function (): void {
            $conversation = Conversation::create(['contract_id' => Contract::where('freelancer_id', $this->users['hossein']->id)->value('id')]);
            $message = $conversation->messages()->create([
                'sender_id' => $this->users['hossein']->id,
                'body' => 'نسخه‌ی کامل را در این لینک گذاشته‌ام.',
                'status' => MessageStatus::Blocked,
                'blocked_reason' => 'link',
            ]);
            $message->violations()->create([
                'user_id' => $this->users['hossein']->id,
                'violation_type' => ViolationType::Link,
                'detected_content' => 'drive.google.com/…',
                'detected_by' => ViolationSource::AutoFilter,
                'action_taken' => ViolationAction::MessageBlocked,
            ]);
        });
    }

    // ---------------------------------------------------------------- helpers

    private function lastLogins(): void
    {
        $recent = [
            'admin' => 0.1, 'support' => 1, 'finance' => 2, 'mentor' => 0.3, 'mentor2' => 1.5,
            'freelancer' => 0.2, 'hossein' => 3, 'zahra' => 0.8, 'fatemeh' => 1, 'ali' => 0.5,
            'employer' => 0.4, 'shop' => 2, 'startup' => 1.2, 'agency' => 12,
        ];

        foreach ($recent as $username => $daysAgo) {
            $this->users[$username]->forceFill(['last_login_at' => $this->now->subMinutes((int) ($daysAgo * 24 * 60))])->save();
        }
    }

    /**
     * Run $callback as if it were $daysAgo days ago, so created_at columns spread over time.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(int $daysAgo, callable $callback, int $hour = 10): mixed
    {
        $moment = $this->now->setTimezone(self::LOCAL_TIMEZONE)->subDays($daysAgo)->setTime($hour, 7 * $daysAgo % 60);

        // Today's later hours have not happened yet: move them just before now (keeping their order),
        // with room for the "+1 hour" / "+5 hours" steps some records take, so nothing is in the future.
        $latest = $this->now->subHours(6);

        if ($moment->greaterThan($latest)) {
            $moment = $latest->subMinutes(24 - $hour);
        }

        Carbon::setTestNow($moment->utc());

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
