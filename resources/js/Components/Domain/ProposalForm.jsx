import { usePage } from '@inertiajs/react';
import ModalForm from '../Form/ModalForm';
import NumberInput from '../Form/NumberInput';
import Switch from '../Form/Switch';
import Textarea from '../Form/Textarea';
import { Badge } from '../UI/Badge';
import { formatDate, formatNumber } from '../../lib/format';
import { budgetText } from '../../lib/project';
import { fillRoute } from '../../lib/text';

const TIPS = ['نیاز کارفرما را با کلمات خودت خلاصه کن', 'بگو دقیقاً چه چیزی تحویل می‌دهی', 'یک نمونه‌کار مرتبط را نام ببر', 'قیمت و زمان واقع‌بینانه بده'];

/**
 * Send a new proposal (`project` + `storeUrl`) or edit a pending one (`proposal` + `updateUrl` template).
 * The freelancer decides here whether they want a mentor on this project; the form also reminds them
 * that sharing contact details is against the platform rules.
 */
export default function ProposalForm({ modal, storeUrl = null, updateUrl = null }) {
    const { project, proposal } = modal.record ?? {};
    const { fees } = usePage().props;
    const editing = Boolean(proposal);
    const target = project ?? proposal?.project;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? 'ویرایش پیشنهاد' : 'ارسال پیشنهاد'}
            subtitle={target?.title}
            icon="bi-send"
            size="lg"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(updateUrl, proposal.id) : storeUrl}
            initial={{
                ...(editing ? {} : { project_id: project?.id ?? '' }),
                cover_letter: proposal?.cover_letter ?? '',
                proposed_price: proposal?.proposed_price ?? project?.budget_min ?? '',
                delivery_days: proposal?.delivery_days ?? '',
                mentorship_requested: proposal?.mentorship_requested ?? false,
            }}
            transform={(data) => ({ ...data, proposed_price: Number(data.proposed_price || 0), delivery_days: Number(data.delivery_days || 0) })}
            submitLabel={editing ? 'ذخیره‌ی پیشنهاد' : 'ارسال پیشنهاد'}
            submitIcon="bi-send"
        >
            {(form) => (
                <div className="row g-3">
                    {target && (
                        <div className="col-12">
                            <div className="jl-project-brief">
                                <div className="d-flex flex-wrap gap-2 align-items-center">
                                    <span className="jl-chip">
                                        <i className="bi bi-cash" /> بودجه: {budgetText(target)}
                                    </span>
                                    {target.deadline && (
                                        <span className="jl-chip">
                                            <i className="bi bi-calendar-event" /> مهلت: {formatDate(target.deadline)}
                                        </span>
                                    )}
                                    {target.proposals_count !== undefined && (
                                        <span className="jl-chip">
                                            <i className="bi bi-people" /> {formatNumber(target.proposals_count)} پیشنهاد
                                        </span>
                                    )}
                                    {target.is_beginner_friendly && <Badge tone="success">مناسب تازه‌کار</Badge>}
                                </div>
                                {target.description && <p className="small text-muted-2 mt-2 mb-0 jl-clamp-3">{target.description}</p>}
                            </div>
                        </div>
                    )}
                    <Textarea
                        form={form}
                        name="cover_letter"
                        label="متن پیشنهاد"
                        required
                        rows={7}
                        minLength={30}
                        maxLength={5000}
                        className="col-12"
                        placeholder="سلام! من ... . برای این پروژه ... انجام می‌دهم و خروجی نهایی ... است."
                    />
                    <NumberInput form={form} name="proposed_price" label="قیمت پیشنهادی" money required className="col-md-6" />
                    <NumberInput form={form} name="delivery_days" label="زمان تحویل" unit="روز" required className="col-md-6" />
                    <Switch
                        form={form}
                        name="mentorship_requested"
                        className="col-12"
                        label="برای این پروژه منتور می‌خواهم"
                        description={`اگر استخدام شوی، یک منتور باتجربه در طول پروژه همراهت است. کارمزد پلتفرم در این حالت ${formatNumber(fees?.mentorship ?? 5)}٪ بیشتر است؛ دو منتورینگ اول تازه‌کارها رایگان است. کارفرما از این انتخاب باخبر نمی‌شود.`}
                    />
                    <div className="col-12">
                        <div className="small text-muted d-flex flex-wrap gap-3">
                            {TIPS.map((tip) => (
                                <span key={tip}>
                                    <i className="bi bi-lightbulb text-accent" /> {tip}
                                </span>
                            ))}
                        </div>
                        <div className="small text-muted mt-2">
                            <i className="bi bi-shield-lock" /> شماره‌ی تماس، ایمیل و لینک شبکه‌های اجتماعی در پیشنهاد مجاز نیست؛ همه‌ی ارتباط‌ها در پلتفرم می‌ماند.
                        </div>
                    </div>
                </div>
            )}
        </ModalForm>
    );
}
