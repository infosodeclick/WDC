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
        const trigger = event.target.closest('[data-directory-open]');

        if (trigger && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            trigger.click();
            return;
        }

        if (event.key === 'Escape' && ! directoryModal.hidden) {
            closeDirectoryModal();
        }
    });
}

const smartflowTemplateSelect = document.querySelector('[data-smartflow-template-select]');
const smartflowFieldsets = document.querySelectorAll('[data-smartflow-fieldsets] [data-template-id]');

if (smartflowTemplateSelect && smartflowFieldsets.length > 0) {
    const syncSmartflowFieldsets = () => {
        smartflowFieldsets.forEach((fieldset) => {
            const isActive = fieldset.dataset.templateId === smartflowTemplateSelect.value;

            fieldset.hidden = ! isActive;
            fieldset.querySelectorAll('input, select, textarea, button').forEach((input) => {
                input.disabled = ! isActive;
            });
        });
    };

    smartflowTemplateSelect.addEventListener('change', syncSmartflowFieldsets);
    syncSmartflowFieldsets();
}

document.addEventListener('DOMContentLoaded', () => {
    const announcementModal = document.querySelector('[data-auto-open-announcement-modal]');

    if (! announcementModal) {
        return;
    }

    if (announcementModal.parentElement !== document.body) {
        document.body.appendChild(announcementModal);
    }

    const carousel = announcementModal.querySelector('#announcementEntryCarousel');
    const slides = Array.from(announcementModal.querySelectorAll('.carousel-item'));
    const dots = Array.from(announcementModal.querySelectorAll('[data-announcement-popup-dot]'));
    const fallbackBackdropClass = 'announcement-popup-fallback-backdrop';
    let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('active')));

    const removeFallbackBackdrop = () => {
        document.querySelectorAll(`.${fallbackBackdropClass}`).forEach((backdrop) => backdrop.remove());
    };

    const closePopup = () => {
        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(announcementModal).hide();
        }

        announcementModal.classList.remove('show');
        announcementModal.style.display = 'none';
        announcementModal.setAttribute('aria-hidden', 'true');
        announcementModal.removeAttribute('aria-modal');
        announcementModal.removeAttribute('role');
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        removeFallbackBackdrop();
    };

    const showPopup = () => {
        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(announcementModal, {
                backdrop: true,
                keyboard: true,
            }).show();
            return;
        }

        announcementModal.style.display = 'block';
        announcementModal.removeAttribute('aria-hidden');
        announcementModal.setAttribute('aria-modal', 'true');
        announcementModal.setAttribute('role', 'dialog');
        announcementModal.classList.add('show');
        document.body.classList.add('modal-open');

        if (! document.querySelector(`.${fallbackBackdropClass}`)) {
            const backdrop = document.createElement('div');
            backdrop.className = `modal-backdrop fade show ${fallbackBackdropClass}`;
            document.body.appendChild(backdrop);
        }
    };

    const syncDots = () => {
        dots.forEach((dot, index) => {
            const isActive = index === activeIndex;
            dot.classList.toggle('active', isActive);
            dot.toggleAttribute('aria-current', isActive);
        });
    };

    const showSlide = (nextIndex) => {
        if (slides.length === 0) {
            return;
        }

        activeIndex = (nextIndex + slides.length) % slides.length;

        slides.forEach((slide, index) => {
            slide.classList.toggle('active', index === activeIndex);
        });

        syncDots();
    };

    announcementModal.querySelectorAll('[data-announcement-popup-close], [data-bs-dismiss="modal"]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closePopup();
        });
    });

    announcementModal.addEventListener('click', (event) => {
        if (event.target === announcementModal) {
            closePopup();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (! announcementModal.classList.contains('show')) {
            return;
        }

        if (event.key === 'Escape') {
            closePopup();
        }

        if (event.key === 'ArrowLeft') {
            showSlide(activeIndex - 1);
        }

        if (event.key === 'ArrowRight') {
            showSlide(activeIndex + 1);
        }
    });

    announcementModal.querySelector('[data-announcement-popup-prev]')?.addEventListener('click', (event) => {
        if (! window.bootstrap?.Carousel) {
            event.preventDefault();
            showSlide(activeIndex - 1);
        }
    });

    announcementModal.querySelector('[data-announcement-popup-next]')?.addEventListener('click', (event) => {
        if (! window.bootstrap?.Carousel) {
            event.preventDefault();
            showSlide(activeIndex + 1);
        }
    });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', (event) => {
            if (! window.bootstrap?.Carousel) {
                event.preventDefault();
                showSlide(index);
            }
        });
    });

    if (window.bootstrap?.Carousel && carousel) {
        window.bootstrap.Carousel.getOrCreateInstance(carousel, {
            interval: false,
            ride: false,
            touch: true,
            wrap: true,
        });

        carousel.addEventListener('slid.bs.carousel', (event) => {
            activeIndex = event.to ?? activeIndex;
            syncDots();
        });
    }

    syncDots();
    showPopup();
});
