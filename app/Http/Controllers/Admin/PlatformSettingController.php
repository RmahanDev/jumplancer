<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExperienceLevel;
use App\Enums\SettingValueType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformSettingResource;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Business rules editable without a deploy (free postings, exam fees, contact-sharing policy ...).
 */
class PlatformSettingController extends Controller
{
    /**
     * Allowed values of the text settings that drive business logic.
     *
     * @return array<string, list<string>>
     */
    public static function choices(): array
    {
        return [
            'exam_required_from_level' => array_column(ExperienceLevel::cases(), 'value'),
            'contact_violation_action' => ['suspend', 'warning', 'block_message'],
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => PlatformSettingResource::collection(PlatformSetting::with('editor')->orderBy('id')->get()),
            'choices' => self::choices(),
            'routes' => [
                'update' => route('admin.settings.update', ':id'),
            ],
        ]);
    }

    public function update(Request $request, PlatformSetting $platformSetting): RedirectResponse
    {
        $rules = match ($platformSetting->value_type) {
            SettingValueType::Int => ['required', 'integer', 'min:0', 'max:100000'],
            SettingValueType::Money => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            SettingValueType::Bool => ['required', 'boolean'],
            SettingValueType::Text => ['required', 'string', 'max:255'],
            SettingValueType::Percent => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        };

        if ($choices = self::choices()[$platformSetting->setting_key] ?? null) {
            $rules[] = Rule::in($choices);
        }

        // Whole-number percentages (the hiring deposit) stay between 1 and 100.
        if ($platformSetting->value_type === SettingValueType::Int && str_ends_with($platformSetting->setting_key, '_percent')) {
            array_push($rules, 'min:1', 'max:100');
        }

        // The mentor is paid out of the fees, so their share can never be more than the fees taken.
        $fees = PlatformSetting::fees();
        $rules[] = match ($platformSetting->setting_key) {
            'mentor_share_percent' => 'max:'.($fees['platform'] + $fees['mentorship']),
            'platform_fee_percent' => 'max:'.(100 - $fees['mentorship']),
            'mentorship_fee_percent' => 'max:'.(100 - $fees['platform']),
            default => 'nullable',
        };

        $validated = $request->validate(['setting_value' => $rules]);
        $value = $validated['setting_value'];

        $platformSetting->update([
            'setting_value' => match (true) {
                $value === null => null,
                $platformSetting->value_type === SettingValueType::Bool => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
                default => (string) $value,
            },
            'updated_by' => $request->user()->id,
        ]);

        $this->toast(__('Setting ":key" was saved.', ['key' => $platformSetting->setting_key]));

        return back();
    }
}
