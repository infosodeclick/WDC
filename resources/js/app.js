import './bootstrap';

const copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
};

document.addEventListener('click', async (event) => {
    const copyButton = event.target.closest('[data-copy]');

    if (! copyButton) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const originalIcon = copyButton.innerHTML;

    try {
        await copyText(copyButton.dataset.copy || '');
        copyButton.classList.add('copied');
        copyButton.innerHTML = '<i class="bi bi-check2"></i>';
        window.setTimeout(() => {
            copyButton.classList.remove('copied');
            copyButton.innerHTML = originalIcon;
        }, 1100);
    } catch {
        copyButton.classList.remove('copied');
    }
});

const directoryModal = document.querySelector('[data-directory-modal]');

if (directoryModal) {
    const modalContent = directoryModal.querySelector('[data-directory-modal-content]');
    const closeButtons = directoryModal.querySelectorAll('[data-directory-close]');
    let lastTrigger = null;

    const closeDirectoryModal = () => {
        directoryModal.hidden = true;
        directoryModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('directory-modal-open');
        modalContent.innerHTML = '';
        lastTrigger?.focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-directory-open]');

        if (! trigger) {
            return;
        }

        const card = trigger.closest('[data-directory-card]');
        const source = card?.querySelector('.directory-modal-source');

        if (! source) {
            return;
        }

        lastTrigger = trigger;
        modalContent.innerHTML = source.innerHTML;
        directoryModal.hidden = false;
        directoryModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('directory-modal-open');
        directoryModal.querySelector('.directory-modal-close')?.focus();
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeDirectoryModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! directoryModal.hidden) {
            closeDirectoryModal();
        }
    });
}
