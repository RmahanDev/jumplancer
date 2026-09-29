import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import RadioCards from '../../../Components/Form/RadioCards';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge } from '../../../Components/UI/Badge';
import { useModal } from '../../../hooks/useModal';
import { formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { label, SETTINGS } from '../../../lib/labels';
import { fillRoute, toLatinDigits } from '../../../lib/text';

function choiceLabel(key, value) {
    const meta = SETTINGS[key];

    if (meta?.choiceLabels) {
        return meta.choiceLabels[value] ?? value;
    }

    if (meta?.choices) {
        return label(meta.choices, value);
    }

    return value;
}

function displayValue(setting) {
    const meta = SETTINGS[setting.setting_key] ?? {};

    if (setting.setting_value === null || setting.setting_value === '') {
        return <Badge tone="warning">تعیین نشده</Badge>;
    }

    switch (setting.value_type) {
        case 'money':
            return Number(setting.setting_value) === 0 ? 'رایگان' : formatMoney(setting.setting_value);
        case 'int':
            return `${formatNumber(setting.setting_value)} ${meta.unit ?? ''}`;
        case 'percent':
            return `${formatNumber(Number(setting.setting_value))}٪`;
        case 'bool':
            return setting.typed_value ? 'روشن' : 'خاموش';
        default:
            return choiceLabel(setting.setting_key, setting.setting_value);
    }
}

function SettingForm({ modal, choices, routes }) {
    const setting = modal.record;
    const meta = SETTINGS[setting?.setting_key] ?? {};
    const allowed = setting ? choices[setting.setting_key] : null;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={meta.label ?? setting?.setting_key ?? 'تنظیم'}
            subtitle={meta.help ?? setting?.description}
            icon={meta.icon ?? 'bi-sliders'}
            size={allowed ? 'lg' : null}
            method="put"
            url={setting ? fillRoute(routes.update, setting.id) : ''}
            initial={{ setting_value: setting?.value_type === 'bool' ? Boolean(setting?.typed_value) : (setting?.setting_value ?? '') }}
            transform={(data) => ({ setting_value: setting?.value_type === 'money' && data.setting_value === '' ? null : data.setting_value })}
        >
            {(form) => {
                if (!setting) {
                    return null;
                }

                if (allowed) {
                    return (
                        <RadioCards
                            form={form}
                            name="setting_value"
                            label="مقدار"
                            columns={allowed.length > 3 ? 2 : allowed.length}
                            options={allowed.map((value) => ({ value, label: choiceLabel(setting.setting_key, value) }))}
                        />
                    );
                }

                if (setting.value_type === 'bool') {
                    return <Switch form={form} name="setting_value" label={meta.label ?? setting.setting_key} description={meta.help} />;
                }

                if (setting.value_type === 'money') {
                    return <NumberInput form={form} name="setting_value" label="مبلغ" money hint="خالی یعنی تعیین نشده؛ صفر یعنی رایگان." />;
                }

                if (setting.value_type === 'percent') {
                    return (
                        <TextInput
                            name="setting_value"
                            label="درصد"
                            required
                            ltr
                            unit="٪"
                            inputMode="decimal"
                            placeholder="3.5"
                            hint="تا دو رقم اعشار، مثلاً ۳٫۵"
                            value={form.data.setting_value}
                            error={form.errors.setting_value}
                            onChange={(value) => {
                                form.setData('setting_value', toLatinDigits(value).replace(/[٫,]/g, '.').replace(/[^\d.]/g, ''));
                                form.clearErrors('setting_value');
                            }}
                        />
                    );
                }

                if (setting.value_type === 'int') {
                    return <NumberInput form={form} name="setting_value" label="مقدار" unit={meta.unit} required />;
                }

                return <TextInput form={form} name="setting_value" label="مقدار" required />;
            }}
        </ModalForm>
    );
}

export default function Index({ settings, choices, routes }) {
    const editor = useModal();

    return (
        <>
            <PageHeader title="تنظیمات پلتفرم" description="قوانین کسب‌وکار که بدون تغییر کد عوض می‌شوند. هر تغییر با نام ویرایشگر ثبت می‌شود." />

            <div className="row g-3">
                {settings.map((setting, index) => {
                    const meta = SETTINGS[setting.setting_key] ?? {};
                    const unset = setting.setting_value === null || setting.setting_value === '';

                    return (
                        <div className="col-md-6 col-xl-4" key={setting.id}>
                            <button
                                type="button"
                                className={`jl-card jl-card-hover jl-setting-card h-100 w-100 text-start jl-rise jl-rise-${Math.min(index + 1, 8)} ${unset ? 'is-unset' : ''}`}
                                onClick={() => editor.show(setting)}
                            >
                                <div className="jl-card-body d-flex flex-column h-100">
                                    <div className="d-flex align-items-start gap-2 mb-2">
                                        <span className={`jl-stat-icon ${unset ? 'is-accent' : ''}`}>
                                            <i className={`bi ${meta.icon ?? 'bi-sliders'}`} />
                                        </span>
                                        <div className="min-w-0">
                                            <div className="fw-bold">{meta.label ?? setting.setting_key}</div>
                                            <code className="small text-muted">{setting.setting_key}</code>
                                        </div>
                                    </div>
                                    <p className="small text-muted-2 mb-3">{meta.help ?? setting.description}</p>
                                    <div className="mt-auto d-flex align-items-end justify-content-between gap-2">
                                        <div className="jl-setting-value">{displayValue(setting)}</div>
                                        <span className="small text-muted text-end">
                                            {setting.editor ? `${setting.editor.name} · ${formatRelative(setting.updated_at)}` : 'مقدار پیش‌فرض'}
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    );
                })}
            </div>

            <SettingForm modal={editor} choices={choices} routes={routes} />
        </>
    );
}
