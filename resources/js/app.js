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

const captureSelectOptions = (select) => Array.from(select.options).map((option) => ({
    value: option.value,
    text: option.textContent,
    dataset: { ...option.dataset },
}));

const uniqueBy = (items, key) => {
    const seen = new Set();

    return items.filter((item) => {
        const value = item[key] || '';

        if (seen.has(value)) {
            return false;
        }

        seen.add(value);

        return true;
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-onboarding-form]').forEach((form) => {
        const positionSelect = form.querySelector('[data-onboarding-position]');
        const departmentSelect = form.querySelector('[data-onboarding-department]');
        const teamSelect = form.querySelector('[data-onboarding-team]');
        const locationSelect = form.querySelector('[data-onboarding-location]');
        const businessUnitInput = form.querySelector('[data-onboarding-business-unit]');

        if (! positionSelect || ! departmentSelect || ! teamSelect || ! locationSelect || ! businessUnitInput) {
            return;
        }

        let salesAssignments = [];

        try {
            salesAssignments = JSON.parse(form.dataset.salesAssignments || '[]');
        } catch {
            salesAssignments = [];
        }

        if (salesAssignments.length === 0) {
            return;
        }

        const salesPositions = new Set(salesAssignments.map((assignment) => assignment.position));
        const originalDepartments = captureSelectOptions(departmentSelect);
        const originalTeams = captureSelectOptions(teamSelect);
        const originalLocations = captureSelectOptions(locationSelect);

        const renderOptions = (select, options, preferredValue = '', preferredBusinessUnit = '') => {
            select.replaceChildren(...options.map((item) => {
                const option = document.createElement('option');
                option.value = item.value || '';
                option.textContent = item.text || '';

                Object.entries(item.dataset || {}).forEach(([key, value]) => {
                    option.dataset[key] = value;
                });

                return option;
            }));

            const optionList = Array.from(select.options);
            let selectedIndex = -1;

            if (preferredBusinessUnit) {
                selectedIndex = optionList.findIndex((option) => option.dataset.businessUnit === preferredBusinessUnit);
            }

            if (selectedIndex < 0 && preferredValue) {
                selectedIndex = optionList.findIndex((option) => option.value === preferredValue);
            }

            if (selectedIndex < 0) {
                selectedIndex = optionList.findIndex((option) => option.value !== '');
            }

            select.selectedIndex = Math.max(selectedIndex, 0);
        };

        const selectedBusinessUnit = () => {
            const selectedOption = departmentSelect.selectedOptions[0];

            if (! selectedOption?.value) {
                return '';
            }

            return selectedOption.dataset.businessUnit
                || businessUnitInput.value
                || selectedOption.textContent
                || '';
        };

        const setBusinessUnit = () => {
            businessUnitInput.value = selectedBusinessUnit();
        };

        const salesRowsForCurrentPosition = () => salesAssignments
            .filter((assignment) => assignment.position === positionSelect.value);

        const syncLocations = () => {
            if (! salesPositions.has(positionSelect.value)) {
                setBusinessUnit();
                return;
            }

            const rows = salesRowsForCurrentPosition()
                .filter((assignment) => assignment.business_unit === selectedBusinessUnit())
                .filter((assignment) => assignment.team === teamSelect.value);
            const locationOptions = [
                originalLocations[0],
                ...uniqueBy(rows, 'location').map((assignment) => ({
                    value: assignment.location,
                    text: assignment.location,
                    dataset: {},
                })),
            ];

            renderOptions(locationSelect, locationOptions, locationSelect.value);
            setBusinessUnit();
        };

        const syncTeams = () => {
            if (! salesPositions.has(positionSelect.value)) {
                setBusinessUnit();
                return;
            }

            const rows = salesRowsForCurrentPosition()
                .filter((assignment) => assignment.business_unit === selectedBusinessUnit());
            const teamOptions = [
                originalTeams[0],
                ...uniqueBy(rows, 'team').map((assignment) => ({
                    value: assignment.team,
                    text: assignment.team,
                    dataset: {},
                })),
            ];

            renderOptions(teamSelect, teamOptions, teamSelect.value);
            syncLocations();
        };

        const syncSalesDropdowns = () => {
            if (! salesPositions.has(positionSelect.value)) {
                renderOptions(departmentSelect, originalDepartments, departmentSelect.value);
                renderOptions(teamSelect, originalTeams, teamSelect.value);
                renderOptions(locationSelect, originalLocations, locationSelect.value);
                setBusinessUnit();
                return;
            }

            const businessUnitOptions = [
                originalDepartments[0],
                ...uniqueBy(salesRowsForCurrentPosition(), 'business_unit').map((assignment) => ({
                    value: assignment.department_id?.toString() || '',
                    text: assignment.business_unit,
                    dataset: {
                        businessUnit: assignment.business_unit,
                    },
                })),
            ];

            renderOptions(departmentSelect, businessUnitOptions, departmentSelect.value, businessUnitInput.value);
            setBusinessUnit();
            syncTeams();
        };

        positionSelect.addEventListener('change', syncSalesDropdowns);
        departmentSelect.addEventListener('change', syncTeams);
        teamSelect.addEventListener('change', syncLocations);
        locationSelect.addEventListener('change', setBusinessUnit);

        syncSalesDropdowns();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const openTargetedDetails = () => {
        const hash = window.location.hash;

        if (! hash) {
            return;
        }

        const target = document.querySelector(hash);

        if (target instanceof HTMLDetailsElement) {
            target.open = true;
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        const details = target?.querySelector('details');

        if (details instanceof HTMLDetailsElement) {
            details.open = true;
        }
    };

    window.addEventListener('hashchange', openTargetedDetails);
    openTargetedDetails();

    document.querySelectorAll('.hr-directory-modal').forEach((modal) => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    document.querySelectorAll('.admin-member-modal').forEach((modal) => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    document.querySelectorAll('[data-permission-editor]').forEach((form) => {
        const roleSelect = form.querySelector('[data-role-select]');
        const countTarget = form.querySelector('[data-permission-count]');

        if (! roleSelect) {
            return;
        }

        let rolePermissions = {};

        try {
            rolePermissions = JSON.parse(form.dataset.rolePermissions || '{}');
        } catch {
            rolePermissions = {};
        }

        const syncPermissionPreview = () => {
            const roleKeys = new Set(rolePermissions[roleSelect.value] || []);
            const grants = new Set();
            const denies = new Set();

            form.querySelectorAll('[data-permission-grant]:checked').forEach((input) => grants.add(input.value));
            form.querySelectorAll('[data-permission-deny]:checked').forEach((input) => denies.add(input.value));

            const effectiveKeys = new Set([...roleKeys, ...grants]);
            denies.forEach((key) => effectiveKeys.delete(key));

            form.querySelectorAll('[data-permission-key]').forEach((row) => {
                const key = row.dataset.permissionKey;
                const isRolePermission = roleKeys.has(key);
                const isEffective = effectiveKeys.has(key);
                const baseline = row.querySelector('[data-role-baseline]');

                if (baseline) {
                    baseline.checked = isRolePermission;
                }

                row.classList.toggle('permission-row-role-standard', isRolePermission);
                row.classList.toggle('permission-row-effective', isEffective);
            });

            if (countTarget) {
                countTarget.textContent = effectiveKeys.size.toString();
            }
        };

        roleSelect.addEventListener('change', syncPermissionPreview);
        form.querySelectorAll('[data-permission-grant], [data-permission-deny]').forEach((input) => {
            input.addEventListener('change', syncPermissionPreview);
        });
        syncPermissionPreview();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.announcement-attachment-modal').forEach((modal) => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        modal.querySelectorAll('[data-announcement-preview-image]').forEach((image) => {
            image.addEventListener('error', () => {
                const fallback = document.createElement('div');
                const icon = document.createElement('i');
                const title = document.createElement('h3');
                const message = document.createElement('p');
                const link = document.createElement('a');

                fallback.className = 'announcement-attachment-file-preview';
                icon.className = 'bi bi-image';
                title.textContent = image.dataset.fileName || 'ไฟล์แนบ';
                message.textContent = 'ไม่สามารถแสดงรูปนี้ได้ในตัวอย่าง อาจเกิดจากไฟล์ต้นทางไม่พร้อมใช้งานบนเซิร์ฟเวอร์';
                link.className = 'btn btn-primary';
                link.href = image.dataset.fileUrl || image.src;
                link.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> เปิดไฟล์';
                fallback.append(icon, title, message, link);
                image.replaceWith(fallback);
            }, { once: true });
        });
    });
});

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
