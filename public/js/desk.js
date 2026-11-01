const money = (centavos) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(centavos / 100);

const menu = document.querySelector("[data-menu-toggle]");
menu?.addEventListener("click", () => {
    const open = menu.getAttribute("aria-expanded") !== "true";
    menu.setAttribute("aria-expanded", String(open));
    document.querySelector(".sidebar")?.classList.toggle("menu-open", open);
});
document.addEventListener("keydown", (event) => {
    if (
        event.key === "Escape" &&
        menu?.getAttribute("aria-expanded") === "true"
    ) {
        menu.setAttribute("aria-expanded", "false");
        document.querySelector(".sidebar")?.classList.remove("menu-open");
        menu.focus();
    }
});

document.querySelectorAll("[data-auto-submit]").forEach((input) => {
    input.addEventListener("change", () => input.form.requestSubmit());
});

const schedule = document.querySelector("[data-schedule-view]");
const viewButtons = document.querySelectorAll("[data-view]");
const setView = (view) => {
    if (!schedule) return;
    schedule.dataset.scheduleView = view;
    viewButtons.forEach((button) =>
        button.setAttribute(
            "aria-pressed",
            String(button.dataset.view === view),
        ),
    );
};
try {
    setView(
        localStorage.getItem("court-desk-view") === "agenda"
            ? "agenda"
            : "timeline",
    );
} catch {}
viewButtons.forEach((button) =>
    button.addEventListener("click", () => {
        setView(button.dataset.view);
        try {
            localStorage.setItem("court-desk-view", button.dataset.view);
        } catch {}
    }),
);
const timeline = document.querySelector(".timeline-scroll");
const firstReservation = timeline?.querySelector(".timeline-booking");
if (timeline && firstReservation) {
    const slotWidth = parseFloat(
        getComputedStyle(timeline.querySelector(".timeline")).getPropertyValue(
            "--slot-width",
        ),
    );
    const firstColumn = parseInt(firstReservation.style.gridColumn, 10);
    timeline.scrollLeft = Math.max(0, (firstColumn - 4) * slotWidth);
}

const form = document.querySelector("[data-booking-form]");
if (form) {
    const updateSummary = () => {
        const court = form.querySelector("#court_id")?.selectedOptions[0];
        const minutes = Number(form.querySelector("#duration").value);
        const rental = Math.floor(
            (Number(court?.dataset.rate ?? 0) * minutes) / 60,
        );
        const isCash = form.querySelector("#payment_method").value === "cash";
        const paidInput = form.querySelector("#paid_amount");
        paidInput.readOnly = !isCash;
        paidInput.required = isCash;
        if (!isCash) paidInput.value = "0";
        const paid = Math.max(
            0,
            Math.round(Number(paidInput.value || 0) * 100),
        );
        paidInput.setCustomValidity(
            isCash && paid !== rental
                ? "Full payment of " +
                      money(rental) +
                      " is required to confirm this reservation."
                : "",
        );
        const set = (name, value) => {
            const element = document.querySelector(`[data-summary-${name}]`);
            if (element) element.textContent = value;
        };
        set("court", court?.dataset.name ?? "Choose a court");
        set("duration", `${minutes / 60} ${minutes === 60 ? "hour" : "hours"}`);
        set("rental", money(rental));
        set("paid", money(paid));
        set("balance", money(Math.max(0, rental - paid)));
        set(
            "fee",
            form.dataset.feeConfigured === "true"
                ? money(Number(form.dataset.fee))
                : "Not configured",
        );
        set(
            "fee-note",
            "A separate platform fee for the venue. Not added to the player balance.",
        );
        const start = form.querySelector("#starts_at").value;
        if (start) {
            const parsed = new Date(`${start}:00+08:00`);
            const end = new Date(parsed.getTime() + minutes * 60000);
            if (!Number.isNaN(parsed.getTime())) {
                const date = new Intl.DateTimeFormat("en-PH", {
                    timeZone: "Asia/Manila",
                    month: "short",
                    day: "numeric",
                }).format(parsed);
                const time = new Intl.DateTimeFormat("en-PH", {
                    timeZone: "Asia/Manila",
                    hour: "numeric",
                    minute: "2-digit",
                });
                set(
                    "time",
                    `${date} · ${time.format(parsed)}–${time.format(end)}`,
                );
            }
        }
        const guidance = form.querySelector("[data-payment-guidance] span");
        guidance.textContent = isCash
            ? "Confirm only after the exact rental total has been received in cash."
            : "Payment pending: a 15-minute checkout hold is not a confirmed reservation. Submit a receipt for the full amount, then verify funds received.";
        const submitLabel = form.querySelector("[data-submit-label]");
        if (submitLabel)
            submitLabel.textContent = isCash
                ? "Confirm paid reservation"
                : "Continue to payment";
    };
    form.addEventListener("input", updateSummary);
    form.addEventListener("change", updateSummary);
    updateSummary();
}

const errorSummary = document.querySelector("[data-error-summary]");
if (errorSummary) {
    const invalid = document.querySelector("[aria-invalid=true]");
    (invalid ?? errorSummary).focus();
}

document.addEventListener("submit", (event) => {
    const button = event.submitter;
    if (button?.dataset.confirm && !window.confirm(button.dataset.confirm)) {
        event.preventDefault();
        return;
    }
    const submittedForm = event.target;
    if (submittedForm.dataset.submitting === "true") {
        event.preventDefault();
        return;
    }
    submittedForm.dataset.submitting = "true";
    setTimeout(() => {
        if (button) {
            button.dataset.originalLabel = button.innerHTML;
            button.disabled = true;
            button.textContent = "Saving…";
        }
    }, 0);
});
window.addEventListener("pageshow", () => {
    document.querySelectorAll("form").forEach((element) => {
        delete element.dataset.submitting;
    });
    document.querySelectorAll("[data-original-label]").forEach((button) => {
        button.innerHTML = button.dataset.originalLabel;
        button.disabled = false;
        delete button.dataset.originalLabel;
    });
});

const ownerForm = document.querySelector("[data-owner-form]");
if (ownerForm) {
    const mode = ownerForm.querySelector("#owner_mode");
    const fields = ownerForm.querySelector("[data-new-owner-fields]");
    const syncOwnerMode = () => {
        const creating = mode.value === "new";
        fields.hidden = !creating;
        fields.querySelectorAll("input").forEach((input) => {
            input.disabled = !creating;
            input.required = creating;
        });
    };
    mode.addEventListener("change", syncOwnerMode);
    syncOwnerMode();
}

const closureToggle = document.querySelector('[data-closure-toggle]');
const closureEnd = document.querySelector('[data-closure-end]');
if (closureToggle && closureEnd) {
    const syncClosure = () => {
        closureEnd.disabled = !closureToggle.checked;
        closureEnd.required = closureToggle.checked;
    };
    closureToggle.addEventListener('change', syncClosure);
    syncClosure();
}
