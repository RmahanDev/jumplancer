import ModalForm from '../Form/ModalForm';
import Textarea from '../Form/Textarea';
import { formatMoney } from '../../lib/format';
import { fillRoute } from '../../lib/text';

/**
 * Ask an expert to step in (employer or freelancer). Money held for the contract stays in
 * escrow until the expert decides: continue, refund the employer or pay the freelancer.
 */
export default function DisputeForm({ modal, url, viewer = 'employer' }) {
    const contract = modal.record?.contract ?? modal.record;
    const cancelling = Boolean(modal.record?.cancelling);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={cancelling ? 'لغو قرارداد با نظر کارشناس' : 'درخواست بررسی کارشناس'}
            subtitle={contract?.project?.title}
            icon="bi-shield-exclamation"
            tone="danger"
            method="post"
            url={contract ? fillRoute(url, contract.id) : ''}
            initial={{ reason: '' }}
            submitLabel="ثبت درخواست"
            submitTone="danger"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <div className="jl-callout is-warning small">
                        <i className="bi bi-info-circle" />{' '}
                        {cancelling
                            ? `${formatMoney(contract?.deposit_balance ?? 0)} امانت حسن انجام کار (و مرحله‌های تأمین‌شده) در این قرارداد است؛ لغو فقط با نظر کارشناس ممکن است.`
                            : 'تا تصمیم کارشناس پول امانت این قرارداد جابه‌جا نمی‌شود.'}{' '}
                        {viewer === 'employer'
                            ? 'اگر کارشناس تشخیص دهد کار فریلنسر پایین‌تر از حد انتظار بوده، کل مبلغ امانت به کیف پولت برمی‌گردد.'
                            : 'اگر کارشناس تشخیص دهد کار را درست تحویل داده‌ای، مبلغ امانت به تو پرداخت می‌شود.'}
                    </div>
                    <Textarea
                        form={form}
                        name="reason"
                        label="چه اتفاقی افتاده؟"
                        required
                        rows={5}
                        minLength={20}
                        maxLength={2000}
                        placeholder={viewer === 'employer' ? 'مثلاً: مرحله‌ی دوم با شرح کار فرق دارد و فریلنسر پاسخ نمی‌دهد.' : 'مثلاً: کار طبق شرح تحویل شده ولی کارفرما مرحله را تأیید نمی‌کند.'}
                    />
                </div>
            )}
        </ModalForm>
    );
}
