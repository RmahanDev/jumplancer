import Modal, { ModalBody } from '../UI/Modal';
import Kbd from '../UI/Kbd';

const GENERAL = [
    ['mod+k', 'جستجو و اجرای دستورها'],
    ['shift+/', 'همین راهنما'],
    ['n', 'افزودن مورد جدید در صفحه‌ی فعلی'],
    ['/', 'رفتن به کادر جستجوی جدول'],
    ['t', 'تغییر تم روشن / تیره'],
    ['b', 'باز و بسته کردن منوی کناری'],
    ['g s', 'حساب کاربری و امنیت'],
];

const IN_FORMS = [
    ['mod+enter', 'ثبت فرم'],
    ['escape', 'بستن پنجره'],
    ['enter', 'تأیید در پنجره‌ی تأیید'],
];

function Row({ combo, label }) {
    return (
        <div className="jl-shortcut-row">
            <span>{label}</span>
            <Kbd combo={combo} />
        </div>
    );
}

/** "?" shows every shortcut of the current panel, including the "G then X" page jumps. */
export default function ShortcutsHelp({ open, onClose, navigation = [] }) {
    const jumps = navigation.filter((item) => item.shortcut);

    return (
        <Modal open={open} onClose={onClose} title="میان‌برهای صفحه‌کلید" subtitle="با صفحه‌کلید فارسی هم کار می‌کنند." icon="bi-keyboard" size="lg">
            <ModalBody>
                <h3 className="h6 fw-bold mb-2">همه‌جا</h3>
                <div className="jl-shortcuts mb-4">
                    {GENERAL.map(([combo, label]) => (
                        <Row key={combo} combo={combo} label={label} />
                    ))}
                </div>

                {jumps.length > 0 && (
                    <>
                        <h3 className="h6 fw-bold mb-2">رفتن به صفحه‌ها</h3>
                        <div className="jl-shortcuts mb-4">
                            {jumps.map((item) => (
                                <Row key={item.key} combo={item.shortcut} label={item.label} />
                            ))}
                        </div>
                    </>
                )}

                <h3 className="h6 fw-bold mb-2">در فرم‌ها و پنجره‌ها</h3>
                <div className="jl-shortcuts">
                    {IN_FORMS.map(([combo, label]) => (
                        <Row key={combo} combo={combo} label={label} />
                    ))}
                </div>
            </ModalBody>
        </Modal>
    );
}
