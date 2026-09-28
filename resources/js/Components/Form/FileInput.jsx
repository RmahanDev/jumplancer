import { useEffect, useId, useRef, useState } from 'react';
import Field, { useBinding } from './Field';
import { formatBytes, formatNumber } from '../../lib/format';

/** Drag-and-drop file picker. The form value is an array of File objects. */
export default function FileInput({ form = null, name, label, value, onChange, error, hint = null, accept = null, max = 5, className = '' }) {
    const id = useId();
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);
    const binding = useBinding({ form, name, value, onChange, error });
    const files = binding.value ?? [];

    const add = (list) => {
        const next = [...files, ...Array.from(list ?? [])].slice(0, max);
        binding.onChange(next);
    };

    return (
        <Field label={label} htmlFor={id} error={binding.error} hint={hint} className={className}>
            <div
                className={`jl-dropzone ${dragging ? 'is-dragging' : ''}`}
                role="button"
                tabIndex={0}
                onClick={() => inputRef.current?.click()}
                onKeyDown={(event) => (event.key === 'Enter' || event.key === ' ') && inputRef.current?.click()}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    add(event.dataTransfer.files);
                }}
            >
                <i className="bi bi-cloud-arrow-up" />
                <strong className="small">فایل‌ها را اینجا رها کن یا کلیک کن</strong>
                <span className="small text-muted">حداکثر {formatNumber(max)} فایل تصویر یا PDF، هر کدام تا ۵ مگابایت</span>
                <input
                    ref={inputRef}
                    id={id}
                    type="file"
                    className="d-none"
                    multiple={max > 1}
                    accept={accept ?? undefined}
                    onChange={(event) => {
                        add(event.target.files);
                        event.target.value = '';
                    }}
                />
            </div>
            {files.length > 0 && (
                <div className="jl-file-list">
                    {files.map((file, index) => (
                        <FileChip key={`${file.name}-${index}`} file={file} onRemove={() => binding.onChange(files.filter((_, position) => position !== index))} />
                    ))}
                </div>
            )}
        </Field>
    );
}

/** One chosen file: a thumbnail for images (an object URL, released on removal), an icon for PDFs. */
function FileChip({ file, onRemove }) {
    const [preview, setPreview] = useState(null);

    useEffect(() => {
        if (!file.type?.startsWith('image/') || typeof URL.createObjectURL !== 'function') {
            return undefined;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    return (
        <span className="jl-file">
            {preview ? (
                <img src={preview} alt="" className="jl-file-thumb" />
            ) : (
                <i className={`bi ${file.type === 'application/pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-image text-primary-emphasis-soft'}`} />
            )}
            <span dir="auto" className="text-truncate">
                {file.name}
            </span>
            <small className="text-muted">{formatBytes(file.size)}</small>
            <button type="button" className="jl-icon-btn" style={{ width: 26, height: 26 }} onClick={onRemove} aria-label="حذف فایل">
                <i className="bi bi-x" />
            </button>
        </span>
    );
}
