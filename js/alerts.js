// ====================================
// ALERT HANDLING + AUTO TAB SWITCHING
// ====================================

document.addEventListener("DOMContentLoaded", () => {
  const successBanner = document.querySelector(".success-message");
  const url = new URL(window.location);
  const sectionParam = url.searchParams.get("section");

  if (successBanner) {
    // Fade out after 3 seconds
    setTimeout(() => {
      successBanner.classList.add("fade-out");
    }, 3000);

    // If redirected from booking or cancel → switch to “My Requests”
    if (sectionParam === "request") {
      setTimeout(() => {
        document.getElementById("requestsLink")?.click();
      }, 400);
    }

    // Clean the URL *after* navigation finishes
    setTimeout(() => {
      if (window.history.replaceState) {
        url.searchParams.delete("success");
        url.searchParams.delete("section");
        window.history.replaceState({}, document.title, url);
      }
    }, 1000);
  }
});