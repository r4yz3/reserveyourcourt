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
