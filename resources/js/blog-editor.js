import '../css/blog-editor.css';
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';
import { journalNodes, installJournalInserts } from './blog-components';

const form = document.querySelector('#journal-form');
if (form) {
    const source = form.querySelector('#content');
    const feedback = form.querySelector('#editor-feedback');
    const controls = form.querySelector('[data-editor-controls]');
    let mode = 'visual';
    const ProductMarker = Node.create({
        name: 'productMarker', group: 'block', atom: true,
        addAttributes() { return { productId: { default: null, parseHTML: el => el.getAttribute('data-qammaris-product') } }; },
        parseHTML() { return [{ tag: 'div[data-qammaris-product]' }]; },
        renderHTML({ node, HTMLAttributes }) { return ['div', mergeAttributes(HTMLAttributes, { 'data-qammaris-product': node.attrs.productId }), 'Tautan produk katalog']; },
    });
    try {
        const editor = new Editor({
            element: form.querySelector('[data-visual-editor]'),
            extensions: [StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: { openOnClick: false } }), TableKit, Image, ProductMarker, ...journalNodes],
            content: source.value,
            editorProps: { attributes: { role: 'textbox', 'aria-label': 'Isi artikel visual', 'aria-multiline': 'true', 'aria-describedby': 'content-error editor-feedback', 'aria-invalid': source.getAttribute('aria-invalid') } },
            onUpdate: ({ editor }) => { if (mode === 'visual') source.value = editor.getHTML(); },
            onSelectionUpdate: ({ editor }) => updateToolbar(editor),
        });
        function updateToolbar(editor) {
            form.querySelectorAll('[data-command]').forEach(button => {
                const command = button.dataset.command;
                const active = command === 'h2' || command === 'h3' ? editor.isActive('heading', { level: Number(command.slice(1)) }) : editor.isActive(command);
                button.setAttribute('aria-pressed', String(active));
                if (command === 'undo' || command === 'redo') button.disabled = !editor.can()[command]();
            });
        }
        controls.hidden = false;
        installJournalInserts(form, editor, feedback, () => mode);
        source.hidden = true;
        form.querySelector('[data-product-insert]').hidden = false;
        feedback.textContent = 'Editor visual siap. Isian baru disimpan ketika Anda menekan Simpan.';
        updateToolbar(editor);
        form.parentElement.querySelectorAll('.journal-errors a[href^="#"]').forEach(link => link.addEventListener('click', event => {
            const field = form.querySelector(link.getAttribute('href'));
            if (!field) return;
            event.preventDefault();
            const section = field.closest('details');
            if (section) section.open = true;
            if (field === source && mode === 'visual') editor.commands.focus();
            else field.focus();
        }));
        form.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', () => {
            const next = button.dataset.mode;
            if (next === mode) return;
            if (next === 'visual') editor.commands.setContent(source.value, { emitUpdate: false });
            mode = next;
            source.hidden = mode === 'visual';
            form.querySelector('[data-visual-editor]').hidden = mode === 'html';
            form.querySelector('[data-editor-toolbar]').hidden = mode === 'html';
            form.querySelector('[data-editor-inserts]').hidden = mode === 'html';
            form.querySelectorAll('[data-mode]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
            feedback.textContent = mode === 'html' ? 'HTML dibersihkan saat preview dan penyimpanan.' : 'Mode visual aktif. HTML sumber tetap dipertahankan sampai isi diedit.';
        }));
        form.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => {
            const command = button.dataset.command;
            const chain = editor.chain().focus();
            if (command === 'paragraph') chain.setParagraph().run();
            else if (command === 'h2' || command === 'h3') chain.toggleHeading({ level: Number(command.slice(1)) }).run();
            else if (command === 'insertTable') chain.insertTable({ rows: 3, cols: 2, withHeaderRow: true }).run();
            else if (['bold', 'italic', 'bulletList', 'orderedList', 'blockquote'].includes(command)) chain['toggle' + command[0].toUpperCase() + command.slice(1)]().run();
            else if (command === 'horizontalRule') chain.setHorizontalRule().run();
            else chain[command]().run();
            updateToolbar(editor);
        }));
        form.querySelector('[data-insert-link]').addEventListener('click', () => {
            const url = form.querySelector('#editor-link').value.trim();
            if (!/^https?:\/\//i.test(url)) { feedback.textContent = 'Masukkan URL http/https yang valid.'; return; }
            editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        });
        form.querySelector('[data-remove-link]').addEventListener('click', () => editor.chain().focus().unsetLink().run());
        form.querySelector('[data-insert-image]').addEventListener('click', () => {
            const src = form.querySelector('#editor-image').value.trim();
            const alt = form.querySelector('#editor-image-alt').value.trim();
            if (!alt || !/^(https:\/\/|\/(?!\/))/.test(src)) { feedback.textContent = 'Masukkan URL gambar HTTPS atau path website dan teks alternatif.'; return; }
            editor.chain().focus().setImage({ src, alt }).run();
        });
        form.querySelector('[data-insert-product]').addEventListener('click', () => {
            const productId = form.querySelector('#editor-product').value;
            if (!productId) { feedback.textContent = 'Pilih produk katalog terlebih dahulu.'; return; }
            if (mode === 'html') { feedback.textContent = 'Pilih mode Visual untuk menambahkan produk.'; return; }
            editor.chain().focus().insertContent({ type: 'productMarker', attrs: { productId } }).run();
            feedback.textContent = 'Tautan produk ditambahkan. Periksa melalui Preview.';
        });
        // Initial loading/mode switches never serialize and rewrite legacy HTML.
        form.addEventListener('submit', event => {
            if (event.submitter?.hasAttribute('data-preview')) return;
            const save = event.submitter;
            if (!save) return;
            save.disabled = true;
            form.querySelector('[data-save-feedback]').textContent = 'Menyimpan artikel…';
            window.setTimeout(() => {
                save.disabled = false;
                form.querySelector('[data-save-feedback]').textContent = 'Belum mendapat respons. Periksa koneksi; isi tetap ada di halaman ini.';
            }, 15000);
        });
        window.addEventListener('pageshow', () => form.querySelectorAll('button[type=submit]').forEach(button => { button.disabled = false; }));
    } catch {
        controls.hidden = true;
        source.hidden = false;
        feedback.textContent = 'Editor visual belum dapat dimuat. Isi tetap tersedia dalam mode HTML; muat ulang untuk mencoba lagi.';
    }
}
