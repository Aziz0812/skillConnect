document.addEventListener('DOMContentLoaded', () => {
  // === Section Navigation ===
  function showSection(sectionId) {
    document.querySelectorAll("main section").forEach(sec => {
      sec.classList.remove("active");
    });
    document.getElementById(sectionId).classList.add("active");

    // Highlight nav
    document.querySelectorAll(".nav-links a").forEach(a => a.classList.remove("active"));
    if (sectionId === "browseSection") document.getElementById("browseLink").classList.add("active");
    if (sectionId === "requestSection") document.getElementById("requestsLink").classList.add("active");

    // Save tab
    localStorage.setItem("activeTab", sectionId);
  }

  document.getElementById("browseLink").addEventListener("click", e => {
    e.preventDefault();
    showSection("browseSection");
  });

  document.getElementById("requestsLink").addEventListener("click", e => {
    e.preventDefault();
    showSection("requestSection");
  });

  // Restore last active tab
  const activeTab = localStorage.getItem("activeTab") || "browseSection";
  showSection(activeTab);

  // === Modal Helpers ===
  function openModal(id) {
    document.getElementById(id).classList.add("show");
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove("show");
  }

  // Close buttons
  document.querySelectorAll(".close-btn, .close-request-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      btn.closest(".modal").classList.remove("show");
    });
  });

  // Click outside to close
  window.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) {
      e.target.classList.remove("show");
    }
  });

  // === Read More Handler ===
  const descModal = document.getElementById("descModal");
  const fullDescription = document.getElementById("fullDescription");

  document.querySelectorAll(".read-more-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      // Keep line breaks instead of one giant block
      fullDescription.innerHTML = btn.getAttribute("data-description")
        .replace(/\n/g, "<br>");
      openModal("descModal");
    });
  });
});
