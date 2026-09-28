import { Link } from '@inertiajs/react';
import SignedAmount from '../../Components/Domain/Money';
import ModalForm from '../../Components/Form/ModalForm';
import NumberInput from '../../Components/Form/NumberInput';
import RadioCards from '../../Components/Form/RadioCards';
import Switch from '../../Components/Form/Switch';
import TextInput from '../../Components/Form/TextInput';
import PageHeader from '../../Components/Panel/PageHeader';
import TableCard from '../../Components/Panel/TableCard';
import { StatusBadge } from '../../Components/UI/Badge';
import { confirm } from '../../Components/UI/ConfirmDialog';
import DataTable from '../../Components/UI/DataTable';
import EmptyState from '../../Components/UI/EmptyState';
import StatCard from '../../Components/UI/StatCard';
import { useCountUp } from '../../hooks/useCountUp';
import { useFilters } from '../../hooks/useFilters';
import { useModal } from '../../hooks/useModal';
import { destroy } from '../../lib/actions';
import { formatCompact, formatDateTime, formatMoney, formatRelative } from '../../lib/format';
import { options } from '../../lib/labels';
import { digitsOnly, fillRoute } from '../../lib/text';

/** "6037997512345678" -> "6037-9975-1234-5678" while typing. */
function groupCard(digits) {
    return (digits.match(/.{1,4}/g) ?? []).join('-');
}

function CardForm({ modal, withdrawal, routes }) {
    const phoneMissing = !withdrawal.account_phone;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="ثبت کارت بانکی"
            subtitle="بازگشت وجه فقط به کارتی واریز می‌شود که به نام خودت و با شماره‌ی موبایل حسابت ثبت شده باشد."
            icon="bi-credit-card-2-front"
            method="post"
            url={routes.cardStore}
            initial={{ card_number: '', holder_name: withdrawal.account_name ?? '', phone: withdrawal.account_phone ?? '', confirm_owner: false }}
            submitLabel="ثبت کارت"
            submitIcon="bi-check2"
        >
            {(form) => (
                <div className="row g-3">
                    {phoneMissing && (
                        <div className="col-12">
                            <div className="jl-callout is-warning">
                                <i className="bi bi-phone" /> اول شماره‌ی موبایلت را در{' '}
                                <Link href={routes.profile} className="fw-semibold">
                                    حساب کاربری
                                </Link>{' '}
                                ثبت کن؛ کارت باید با همین شماره در بانک ثبت شده باشد.
                            </div>
                        </div>
                    )}
                    <TextInput
                        name="card_number"
                        label="شماره‌ی کارت"
                        required
                        ltr
                        className="col-12"
                        icon="bi-credit-card"
                        inputMode="numeric"
                        autoComplete="off"
                        placeholder="6037-9975-xxxx-xxxx"
                        value={groupCard(form.data.card_number)}
                        onChange={(value) => {
                            form.setData('card_number', digitsOnly(value).slice(0, 16));
                            form.clearErrors('card_number');
                        }}
                        error={form.errors.card_number}
                        hint="۱۶ رقم روی کارت؛ بانک از روی شماره تشخیص داده می‌شود."
                    />
                    <TextInput form={form} name="holder_name" label="نام صاحب کارت" required className="col-md-6" icon="bi-person" readOnly hint="همان نام حساب کاربری تو؛ کارت دیگران پذیرفته نمی‌شود." />
                    <TextInput form={form} name="phone" label="موبایل ثبت‌شده برای کارت" required ltr className="col-md-6" icon="bi-phone" inputMode="numeric" placeholder="09xxxxxxxxx" hint="باید همان موبایل حسابت باشد." />
                    <div className="col-12">
                        <label className="form-check d-flex gap-2 align-items-start">
                            <input
                                type="checkbox"
                                className={`form-check-input mt-1 ${form.errors.confirm_owner ? 'is-invalid' : ''}`}
                                checked={Boolean(form.data.confirm_owner)}
                                onChange={(event) => form.setData('confirm_owner', event.target.checked)}
                            />
                            <span className="form-check-label small">تأیید می‌کنم این کارت به نام خودم است و با شماره‌ی موبایل حسابم در بانک ثبت شده.</span>
                        </label>
                        {form.errors.confirm_owner && <div className="invalid-feedback d-block">{form.errors.confirm_owner}</div>}
                    </div>
                </div>
            )}
        </ModalForm>
    );
}

function WithdrawForm({ modal, wallet, cards, withdrawal, routes, onAddCard }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="درخواست بازگشت وجه"
            subtitle="مبلغ از موجودی قابل استفاده کم می‌شود و بعد از بررسی به کارتت واریز می‌شود."
            icon="bi-bank"
            method="post"
            url={routes.withdrawalStore}
            initial={{ bank_card_id: cards[0]?.id ?? '', withdraw_all: false, amount: '' }}
            transform={(data) => ({ ...data, amount: data.withdraw_all ? null : Number(data.amount || 0) })}
            submitLabel="ثبت درخواست"
            submitIcon="bi-send"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <dl className="jl-details mb-0">
                        <dt>قابل برداشت</dt>
                        <dd className="jl-money">{formatMoney(wallet.balance)}</dd>
                        <dt>در امانت (غیرقابل برداشت)</dt>
                        <dd>{formatMoney(wallet.held_balance)}</dd>
                    </dl>

                    <RadioCards
                        form={form}
                        name="bank_card_id"
                        label="واریز به کارت"
                        columns={1}
                        options={cards.map((card) => ({
                            value: card.id,
                            label: <span className="ltr d-inline-block">{card.card_masked}</span>,
                            description: `${card.bank_name ?? 'بانک'} · ${card.holder_name}`,
                            icon: 'bi-credit-card',
                        }))}
                    />
                    <button type="button" className="btn btn-link btn-sm p-0" style={{ justifySelf: 'start' }} onClick={onAddCard}>
                        <i className="bi bi-plus-circle" /> کارت دیگری ثبت کن
                    </button>

                    <Switch form={form} name="withdraw_all" label="کل موجودی قابل برداشت" description={`${formatMoney(wallet.balance)} به کارتت واریز می‌شود.`} />
                    {!form.data.withdraw_all && (
                        <NumberInput
                            form={form}
                            name="amount"
                            label="مبلغ دلخواه"
                            money
                            required
                            hint={`حداقل ${formatMoney(Math.min(withdrawal.minimum, wallet.balance))} و حداکثر ${formatMoney(wallet.balance)}`}
                        />
                    )}

                    <div className="jl-callout is-info small">
                        <i className="bi bi-info-circle" /> پولی که در امانت قراردادهاست (امانت حسن انجام کار یا مرحله‌های تأمین‌شده) برداشتنی نیست. ادمین یا پشتیبان بعد از واریز، شماره‌ی پیگیری و تصویر رسید را برایت ثبت می‌کند.
                    </div>
                </div>
            )}
        </ModalForm>
    );
}

function CardsAndWithdrawals({ cards, withdrawals, routes, onAddCard, onWithdraw, canWithdraw }) {
    const removeCard = async (card) => {
        const ok = await confirm({ title: 'حذف کارت؟', message: `کارت ${card.card_masked} از فهرست کارت‌های واریز حذف می‌شود.`, confirmLabel: 'حذف کارت' });

        if (ok) {
            destroy(fillRoute(routes.cardDestroy, card.id));
        }
    };

    const cancelRequest = async (request) => {
        const ok = await confirm({
            title: 'لغو درخواست بازگشت وجه؟',
            message: `${formatMoney(request.amount)} به موجودی قابل استفاده‌ات برمی‌گردد.`,
            confirmLabel: 'لغو درخواست',
            tone: 'primary',
            icon: 'bi-arrow-counterclockwise',
        });

        if (ok) {
            destroy(fillRoute(routes.withdrawalDestroy, request.id));
        }
    };

    return (
        <div className="row g-3 mb-4">
            <div className="col-lg-5">
                <section className="jl-card h-100 jl-rise jl-rise-2">
                    <header className="jl-card-header">
                        <h2 className="d-flex align-items-center gap-2">
                            <i className="bi bi-credit-card-2-front" /> کارت‌های بانکی من
                        </h2>
                        <button type="button" className="btn btn-soft btn-sm" onClick={onAddCard}>
                            <i className="bi bi-plus-lg" /> کارت جدید
                        </button>
                    </header>
                    <div className="jl-card-body">
                        {cards.length === 0 ? (
                            <EmptyState compact icon="bi-credit-card" title="هنوز کارتی ثبت نکرده‌ای" description="برای بازگشت وجه، یک کارت به نام خودت ثبت کن." />
                        ) : (
                            <ul className="jl-bank-cards">
                                {cards.map((card) => (
                                    <li key={card.id} className="jl-bank-card">
                                        <i className="bi bi-credit-card-2-front jl-bank-card-icon" />
                                        <div className="min-w-0 flex-grow-1">
                                            <div className="fw-semibold ltr text-start num">{card.card_masked}</div>
                                            <div className="small text-muted text-truncate">
                                                {card.bank_name ?? 'بانک نامشخص'} · {card.holder_name} · <span className="ltr">{card.phone}</span>
                                            </div>
                                        </div>
                                        <button type="button" className="jl-icon-btn text-danger" onClick={() => removeCard(card)} aria-label={`حذف کارت ${card.card_masked}`} data-tip="حذف">
                                            <i className="bi bi-trash3" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
            <div className="col-lg-7">
                <section className="jl-card h-100 jl-rise jl-rise-3">
                    <header className="jl-card-header">
                        <h2 className="d-flex align-items-center gap-2">
                            <i className="bi bi-bank" /> درخواست‌های بازگشت وجه
                        </h2>
                        <button type="button" className="btn btn-primary btn-sm" onClick={onWithdraw} disabled={!canWithdraw}>
                            <i className="bi bi-box-arrow-up-left" /> درخواست جدید
                        </button>
                    </header>
                    <div className="jl-card-body">
                        {withdrawals.length === 0 ? (
                            <EmptyState compact icon="bi-bank" title="درخواستی ثبت نشده" description="هر مبلغ دلخواه یا کل موجودی قابل استفاده را می‌توانی به کارتت برگردانی." />
                        ) : (
                            <ul className="jl-withdrawals">
                                {withdrawals.map((request) => (
                                    <li key={request.id} className="jl-withdrawal">
                                        <div className="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                            <div className="min-w-0">
                                                <div className="fw-bold num">{formatMoney(request.amount)}</div>
                                                <div className="small text-muted">
                                                    به <span className="ltr d-inline-block">{request.card_masked}</span> · {formatRelative(request.created_at)}
                                                </div>
                                            </div>
                                            <div className="d-flex align-items-center gap-2">
                                                <StatusBadge group="withdrawalStatus" value={request.status} />
                                                {request.status === 'pending' && (
                                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => cancelRequest(request)}>
                                                        لغو
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        {request.status === 'paid' && (
                                            <div className="jl-withdrawal-proof">
                                                <span>
                                                    <i className="bi bi-hash" /> شماره‌ی پیگیری: <strong className="ltr d-inline-block">{request.tracking_code}</strong>
                                                </span>
                                                {request.receipt_url && (
                                                    <a href={request.receipt_url} target="_blank" rel="noreferrer">
                                                        <i className="bi bi-receipt" /> مشاهده‌ی رسید
                                                    </a>
                                                )}
                                            </div>
                                        )}
                                        {request.status === 'rejected' && request.rejection_reason && (
                                            <div className="small text-danger mt-1">
                                                <i className="bi bi-x-circle" /> {request.rejection_reason}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}

const QUICK_AMOUNTS = [500_000, 1_000_000, 5_000_000, 10_000_000];

function DepositForm({ modal, sandbox, routes }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="شارژ کیف پول"
            subtitle="درگاه آزمایشی: بدون پرداخت واقعی، برای امتحان کردن امانت و قراردادها."
            icon="bi-wallet2"
            method="post"
            url={routes.deposit}
            initial={{ amount: '' }}
            transform={(data) => ({ amount: Number(data.amount || 0) })}
            submitLabel="پرداخت آزمایشی"
            submitIcon="bi-credit-card"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <NumberInput form={form} name="amount" label="مبلغ" money required data-autofocus />
                    <div className="d-flex flex-wrap gap-2">
                        {QUICK_AMOUNTS.map((amount) => (
                            <button key={amount} type="button" className={`jl-chip jl-chip-add ${Number(form.data.amount) === amount ? 'is-active' : ''}`} onClick={() => form.setData('amount', String(amount))}>
                                {formatCompact(amount)} تومان
                            </button>
                        ))}
                    </div>
                    <div className="small text-muted">
                        حداقل ۱۰ هزار و حداکثر {formatMoney(sandbox.max)} در هر شارژ.
                    </div>
                </div>
            )}
        </ModalForm>
    );
}

export default function Wallet({ wallet, totals, transactions, filters: initialFilters, types, sandbox, cards = [], withdrawals = [], withdrawal, routes }) {
    const filters = useFilters(initialFilters);
    const depositor = useModal();
    const cardAdder = useModal();
    const withdrawer = useModal();
    const cardList = cards.data ?? cards;
    const withdrawalList = withdrawals.data ?? withdrawals;
    const canWithdraw = wallet.balance > 0;

    const startWithdrawal = () => (cardList.length === 0 ? cardAdder.show(null) : withdrawer.show(null));
    const addCardFromWithdrawal = () => {
        withdrawer.close();
        window.setTimeout(() => cardAdder.show(null), 180);
    };
    const balance = useCountUp(wallet.balance);

    const columns = [
        {
            key: 'description',
            label: 'شرح',
            primary: true,
            render: (transaction) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 360 }}>
                        {transaction.description ?? '—'}
                    </div>
                    <StatusBadge group="transactionType" value={transaction.type} className="mt-1" />
                </div>
            ),
        },
        { key: 'amount', label: 'مبلغ', render: (transaction) => <SignedAmount amount={transaction.amount} /> },
        { key: 'status', label: 'وضعیت', render: (transaction) => <StatusBadge group="transactionStatus" value={transaction.status} /> },
        { key: 'created_at', label: 'زمان', className: 'text-nowrap', render: (transaction) => <span title={formatDateTime(transaction.created_at)}>{formatRelative(transaction.created_at)}</span> },
    ];

    return (
        <>
            <PageHeader
                title="کیف پول"
                description="موجودی قابل استفاده، پول در امانت، بازگشت وجه به کارت و همه‌ی تراکنش‌ها."
                actions={
                    <button type="button" className="btn btn-soft" onClick={startWithdrawal} disabled={!canWithdraw} title={canWithdraw ? undefined : 'موجودی قابل برداشت نداری'}>
                        <i className="bi bi-box-arrow-up-left" /> درخواست بازگشت وجه
                    </button>
                }
                primary={sandbox.enabled ? { label: 'شارژ کیف پول', icon: 'bi-plus-circle', onClick: () => depositor.show(null) } : null}
            />

            <div className="row g-3 mb-4">
                <div className="col-lg-5">
                    <section className="jl-card jl-wallet-hero h-100 jl-rise">
                        <div className="jl-card-body">
                            <div className="d-flex align-items-center justify-content-between">
                                <span className="jl-wallet-label">موجودی قابل استفاده</span>
                                <i className="bi bi-wallet2 fs-4" />
                            </div>
                            <div className="jl-wallet-balance" title={formatMoney(wallet.balance)}>
                                {formatMoney(balance, { unit: false })}
                                <span>تومان</span>
                            </div>
                            <div className="jl-wallet-held">
                                <i className="bi bi-safe2" /> {formatMoney(wallet.held_balance)} در امانت (امانت حسن انجام کار و مرحله‌های در جریان) — برداشتنی نیست
                            </div>
                            {withdrawal?.pending > 0 && (
                                <div className="jl-wallet-held mt-1">
                                    <i className="bi bi-hourglass-split" /> {formatMoney(withdrawal.pending)} در صف واریز به کارت
                                </div>
                            )}
                        </div>
                    </section>
                </div>
                <div className="col-sm-6 col-lg-3 col-xl">
                    <StatCard label="کل ورودی‌ها" value={totals.income} icon="bi-arrow-down-left-circle" format="money" index={1} foot="شارژ، دریافت از امانت و بازگشت وجه" />
                </div>
                <div className="col-sm-6 col-lg-4 col-xl">
                    <StatCard label="کل خروجی‌ها" value={totals.spent} icon="bi-arrow-up-right-circle" tone="accent" format="money" index={2} foot="امانت، کارمزد و خرید پلن" />
                </div>
            </div>

            <CardsAndWithdrawals
                cards={cardList}
                withdrawals={withdrawalList}
                routes={routes}
                onAddCard={() => cardAdder.show(null)}
                onWithdraw={startWithdrawal}
                canWithdraw={canWithdraw}
            />

            <TableCard
                meta={transactions.meta}
                toolbar={
                    <>
                        <select className="form-select" value={filters.filters.type ?? ''} onChange={(event) => filters.set('type', event.target.value || null)} aria-label="نوع تراکنش">
                            <option value="">همه‌ی تراکنش‌ها</option>
                            {options('transactionType', types).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <span className="small text-muted ms-auto">مبلغ مثبت یعنی پولی که به کیف پولت آمده.</span>
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={transactions.data}
                    empty={
                        <EmptyState
                            icon="bi-receipt"
                            title="هنوز تراکنشی نداری"
                            description={sandbox.enabled ? 'با درگاه آزمایشی کیف پولت را شارژ کن و روند امانت را امتحان کن.' : 'اولین پرداخت یا دریافت اینجا نمایش داده می‌شود.'}
                            action={
                                sandbox.enabled && (
                                    <button type="button" className="btn btn-primary btn-sm" onClick={() => depositor.show(null)}>
                                        <i className="bi bi-plus-circle" /> شارژ کیف پول
                                    </button>
                                )
                            }
                        />
                    }
                />
            </TableCard>

            {sandbox.enabled && <DepositForm modal={depositor} sandbox={sandbox} routes={routes} />}
            <CardForm modal={cardAdder} withdrawal={withdrawal} routes={routes} />
            <WithdrawForm modal={withdrawer} wallet={wallet} cards={cardList} withdrawal={withdrawal} routes={routes} onAddCard={addCardFromWithdrawal} />
        </>
    );
}
