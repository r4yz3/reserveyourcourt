const bookingForm = document.querySelector("[data-public-booking]");
if (bookingForm) {
    const formatMoney = (value) =>
        new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
        }).format(value / 100);
    const update = () => {
        const choice = bookingForm.querySelector("[name=selection]:checked");
        if (!choice) return;
        const value = choice.value.split("|")[1];
        const start = new Date(`${value}:00+08:00`);
        const duration = Number(
            bookingForm.querySelector("[name=duration]").value,
        );
        const end = new Date(start.getTime() + duration * 60000);
        const time = new Intl.DateTimeFormat("en-PH", {
            timeZone: "Asia/Manila",
            hour: "numeric",
            minute: "2-digit",
        });
        bookingForm.querySelector("[data-player-court]").textContent =
            choice.dataset.court;
        bookingForm.querySelector("[data-player-time]").textContent =
            `${time.format(start)}–${time.format(end)} · ${duration} minutes`;
        bookingForm.querySelector("[data-player-total]").textContent =
            formatMoney(Number(choice.dataset.price));
    };
    bookingForm.addEventListener("change", update);
    update();
}
document.addEventListener("submit", (event) => {
    const form = event.target;
    if (form.dataset.submitting === "true") {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = "true";
    const button = event.submitter;
    setTimeout(() => {
        if (button) {
            button.dataset.label = button.innerHTML;
            button.disabled = true;
            button.textContent = "Please wait…";
        }
    }, 0);
});
window.addEventListener("pageshow", () => {
    document
        .querySelectorAll("form")
        .forEach((form) => delete form.dataset.submitting);
    document.querySelectorAll("[data-label]").forEach((button) => {
        button.innerHTML = button.dataset.label;
        button.disabled = false;
        delete button.dataset.label;
    });
});
document.querySelectorAll("[data-score-form]").forEach((form) => {
    const sync = () => {
        const isDoubles =
            form.querySelector("[data-format-input]:checked")?.value ===
            "doubles";
        form.querySelectorAll("[data-doubles-only]").forEach((group) => {
            group.hidden = !isDoubles;
            group
                .querySelectorAll('input[name$="_name"]')
                .forEach((input) => (input.required = isDoubles));
        });
    };
    form.querySelectorAll("[data-format-input]").forEach((input) =>
        input.addEventListener("change", sync),
    );
    sync();
});
document.querySelectorAll("[data-rating-input]").forEach((root) => {
    const number = root.querySelector("[data-rating-number]");
    const range = root.querySelector("[data-rating-range]");
    const label = root.querySelector("[data-rating-label]");
    const bands = root.querySelectorAll("[data-rating-preset]");
    const describe = (value) => {
        if (value === "" || Number.isNaN(Number(value))) return "Not rated";
        const rating = Number(value);
        if (rating < 2.5) return "Beginner";
        if (rating < 3) return "Novice";
        if (rating < 3.5) return "Intermediate";
        if (rating < 4) return "Strong intermediate";
        if (rating < 4.5) return "Advanced";
        return "Expert";
    };
    const render = () => {
        const text = describe(number.value);
        label.textContent = text;
        range.toggleAttribute("data-unset", number.value === "");
        bands.forEach((band) => {
            const isNotRated = band.dataset.ratingPreset === "NR";
            band.setAttribute(
                "aria-pressed",
                String(isNotRated ? number.value === "" : band.textContent.trim() === text),
            );
        });
    };
    range.addEventListener("input", () => {
        number.value = Number(range.value).toFixed(2);
        render();
    });
    number.addEventListener("input", () => {
        if (number.value !== "") range.value = number.value;
        render();
    });
    bands.forEach((band) =>
        band.addEventListener("click", () => {
            const preset = band.dataset.ratingPreset;
            number.value = preset === "NR" ? "" : preset;
            if (preset !== "NR") range.value = preset;
            render();
        }),
    );
});

document.querySelectorAll("[data-profile-prompt]").forEach((dialog) => {
    if (typeof dialog.showModal === "function") dialog.showModal();
});

document.querySelectorAll("[data-auto-submit-file]").forEach((input) =>
    input.addEventListener("change", () => input.files.length && input.form.requestSubmit()),
);

document.querySelectorAll("[data-counter]").forEach((field) => {
    const counter = document.getElementById(field.dataset.counter);
    field.addEventListener("input", () => (counter.textContent = field.value.length));
});

document.querySelectorAll("[data-copy]").forEach((button) =>
    button.addEventListener("click", async () => {
        const field = document.querySelector(button.dataset.copy);
        field.select();
        try {
            await navigator.clipboard.writeText(field.value);
            button.textContent = "Copied";
        } catch {
            button.textContent = "Press Ctrl+C";
        }
        setTimeout(() => (button.textContent = "Copy"), 2000);
    }),
);

document.querySelectorAll("form[data-confirm]").forEach((form) =>
    form.addEventListener("submit", (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }),
);

document.querySelectorAll("[data-wizard]").forEach((form) => {
    const panels = [...form.querySelectorAll("[data-step]")];
    const tabs = [...form.querySelectorAll("[data-step-target]")];
    const back = form.querySelector("[data-wizard-back]");
    const next = form.querySelector("[data-wizard-next]");
    const finals = form.querySelectorAll("[data-wizard-final]");
    const progress = form.querySelector("[data-wizard-progress]");
    let index = Math.max(0, panels.findIndex((panel) => panel.dataset.step === form.dataset.startStep));
    form.classList.add("is-stepped");
    const show = (target) => {
        index = target;
        panels.forEach((panel, i) => (panel.hidden = i !== index));
        tabs.forEach((tab, i) => {
            tab.setAttribute("aria-current", i === index ? "step" : "false");
            tab.classList.toggle("is-complete", i < index);
        });
        back.hidden = index === 0;
        next.hidden = index === panels.length - 1;
        finals.forEach((button) => (button.hidden = index !== panels.length - 1));
        const nextLabel = tabs[index + 1]?.querySelector(".wizard-step-label")?.textContent;
        next.firstChild.textContent = nextLabel ? `Continue to ${nextLabel}` : "Continue";
        progress.textContent = `Step ${index + 1} of ${panels.length}`;
    };
    const stepIsValid = () =>
        [...panels[index].querySelectorAll("input, select, textarea")].every((field) => {
            if (field.closest("[hidden]") || field.disabled) return true;
            if (field.checkValidity()) return true;
            field.reportValidity();
            return false;
        });
    next.addEventListener("click", () => stepIsValid() && show(index + 1));
    back.addEventListener("click", () => show(index - 1));
    tabs.forEach((tab, i) => tab.addEventListener("click", () => (i < index || stepIsValid()) && show(i)));
    show(index);

    const privacyInputs = form.querySelectorAll("[data-privacy-input]");
    const privateOnly = form.querySelector("[data-private-only]");
    const syncPrivacy = () => (privateOnly.hidden = form.querySelector("[data-privacy-input]:checked")?.value !== "private");
    privacyInputs.forEach((input) => input.addEventListener("change", syncPrivacy));

    const venue = form.querySelector("[data-venue-select]");
    const locationField = form.querySelector("[data-location-field]");
    const syncVenue = () => {
        locationField.hidden = venue.value !== "";
        locationField.querySelector("input").required = venue.value === "";
    };
    venue.addEventListener("change", syncVenue);
    syncVenue();
});
document.querySelectorAll("[data-club-select]").forEach((select) => {
    const form = select.form;
    const clubOnly = form.querySelector("[data-club-only]");
    const hint = form.querySelector("[data-club-hint]");
    const sync = () => {
        const hasClub = select.value !== "";
        clubOnly.disabled = !hasClub;
        hint.hidden = hasClub;
        if (!hasClub && clubOnly.checked) {
            form.querySelector('[data-privacy-input][value="public"]').checked = true;
            form.querySelector("[data-private-only]").hidden = true;
        }
    };
    select.addEventListener("change", sync);
    sync();
});

document.querySelectorAll("[data-share-url]").forEach((button) => {
    if (!navigator.share) return;
    button.hidden = false;
    button.addEventListener("click", () =>
        navigator.share({ title: button.dataset.shareTitle, url: button.dataset.shareUrl }).catch(() => {}),
    );
});
