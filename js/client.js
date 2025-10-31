// ==========================
// CLIENT DASHBOARD JAVASCRIPT
// ==========================
document.addEventListener("DOMContentLoaded", () => {
  // === SECTION NAVIGATION ===
  function showSection(sectionId) {
    document.querySelectorAll("main section").forEach(sec => sec.classList.remove("active"));
    document.getElementById(sectionId).classList.add("active");

    // Highlight nav
    document.querySelectorAll(".nav-links a").forEach(a => a.classList.remove("active"));
    if (sectionId === "dashboardSection") document.getElementById("dashboardLink").classList.add("active");
    if (sectionId === "browseSection") document.getElementById("browseLink").classList.add("active");
    if (sectionId === "requestSection") document.getElementById("requestsLink").classList.add("active");

    // Save active section
    localStorage.setItem("activeSection", sectionId);

    // Default to Active tab when showing requests
    if (sectionId === "requestSection") {
      document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
      document.querySelectorAll(".request-section").forEach(sec => sec.classList.remove("active"));
      const activeTab = document.querySelector('.tab-btn[data-tab="active"]');
      const activeSection = document.getElementById("requestSection-active");
      if (activeTab && activeSection) {
        activeTab.classList.add("active");
        activeSection.classList.add("active");
      }
    }

    // Adjust scroll smoothly
    setTimeout(() => {
      const section = document.getElementById(sectionId);
      const h2Element = section.querySelector("h2");
      const navbarHeight = document.querySelector(".top-nav").offsetHeight || 80;
      const h2Top = h2Element.offsetTop;

      window.scrollTo({
        top: h2Top - navbarHeight - 20,
        behavior: "smooth"
      });
    }, 100);
  }

  // === NAV LINKS ===
  const dashboardLink = document.getElementById("dashboardLink");
  const browseLink = document.getElementById("browseLink");
  const requestsLink = document.getElementById("requestsLink");

  if (dashboardLink) {
  dashboardLink.addEventListener("click", e => {
    e.preventDefault();
    showSection("dashboardSection");
    
    // Give the section a short delay before drawing the chart
    setTimeout(() => {
      loadClientDashboard();
    }, 300);
  });
  }

  if (browseLink) {
    browseLink.addEventListener("click", e => {
      e.preventDefault();
      showSection("browseSection");
    });
  }

  if (requestsLink) {
    requestsLink.addEventListener("click", e => {
      e.preventDefault();
      showSection("requestSection");
      setTimeout(animateProgressBars, 500);
    });
  }

  // === INITIAL SECTION LOAD ===
  const urlParams = new URLSearchParams(window.location.search);
  const sectionFromUrl = urlParams.get("section");
  const savedSection = sectionFromUrl || localStorage.getItem("activeSection") || "dashboardSection";
  showSection(savedSection);

  // === MODALS ===
  function openModal(id) {
    document.getElementById(id).classList.add("show");
    document.body.style.overflow = "hidden";
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove("show");
    document.body.style.overflow = "auto";
  }

  document.querySelectorAll(".close-btn").forEach(btn => {
    btn.addEventListener("click", () => closeModal(btn.closest(".modal").id));
  });

  window.addEventListener("click", e => {
    if (e.target.classList.contains("modal")) closeModal(e.target.id);
  });

  document.addEventListener("keydown", e => {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal.show").forEach(modal => closeModal(modal.id));
    }
  });

  // === READ MORE MODAL ===
  const fullDescription = document.getElementById("fullDescription");
  document.querySelectorAll(".read-more-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      fullDescription.innerHTML = btn
        .getAttribute("data-description")
        .replace(/\n/g, "<br>");
      openModal("descModal");
    });
  });

  // === CONTACT PROVIDER MODAL ===
  const contactInfo = document.getElementById("contactInfo");
  document.querySelectorAll(".contact-provider-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const providerName = btn.getAttribute("data-provider");
      const serviceName = btn.getAttribute("data-service");
      contactInfo.innerHTML = `
        <p><strong>Provider:</strong> ${providerName}</p>
        <p><strong>Service:</strong> ${serviceName}</p>
        <p>Contact details and direct messaging features are coming soon!</p>
      `;
      openModal("contactModal");
    });
  });

  // === TOAST NOTIFICATIONS ===
  function showToast(message, type = "success") {
    const container = document.querySelector(".toast-container") || createToastContainer();
    const toast = document.createElement("div");
    toast.className = type === "success" ? "success-message" : "error-message";
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(100%)";
      setTimeout(() => toast.remove(), 500);
    }, 2500);
  }

  function createToastContainer() {
    const div = document.createElement("div");
    div.className = "toast-container";
    div.style.position = "fixed";
    div.style.top = "1rem";
    div.style.right = "1rem";
    div.style.zIndex = "2000";
    document.body.appendChild(div);
    return div;
  }

  // === BOOKING ANIMATION + AJAX ===
  document.querySelectorAll(".book-form").forEach(form => {
    form.addEventListener("submit", function(e) {
      e.preventDefault();
      const card = this.closest(".provider-card");

      card.style.transition = "opacity 0.4s ease, transform 0.4s ease";
      card.style.opacity = "0";
      card.style.transform = "translateY(20px)";
      showToast("Service booked successfully!", "success");

      setTimeout(() => {
        this.submit();
      }, 400);
    });
  });

  document.querySelectorAll(".ajax-book-form").forEach(form => {
    form.addEventListener("submit", function(e) {
      e.preventDefault();
      const btn = form.querySelector(".book-btn");
      btn.disabled = true;
      btn.textContent = "Booking...";

      const skillId = form.querySelector('input[name="book_skill_id"]').value;

      fetch("book_skill.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "skill_id=" + encodeURIComponent(skillId)
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            btn.textContent = "Booked!";
            btn.classList.add("booked");
            setTimeout(() => window.location.reload(), 1000);
          } else {
            btn.textContent = "Book Now";
            btn.disabled = false;
            alert(data.message || "Booking failed.");
          }
        })
        .catch(() => {
          btn.textContent = "Book Now";
          btn.disabled = false;
          alert("Network error.");
        });
    });
  });

  // === CANCEL REQUEST ===
  document.querySelectorAll(".cancel-request-btn").forEach(btn => {
    btn.addEventListener("click", function() {
      if (!confirm("Are you sure you want to cancel this request?")) return;
      const requestId = btn.getAttribute("data-request-id");
      btn.disabled = true;
      btn.textContent = "Cancelling...";

      fetch("cancel_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "request_id=" + encodeURIComponent(requestId)
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            btn.textContent = "Cancelled";
            btn.classList.add("cancelled");
            setTimeout(() => window.location.reload(), 1000);
          } else {
            btn.disabled = false;
            btn.textContent = "Cancel Request";
            alert(data.message || "Cancel failed.");
          }
        })
        .catch(() => {
          btn.disabled = false;
          btn.textContent = "Cancel Request";
          alert("Network error.");
        });
    });
  });

  // === PROGRESS BAR ANIMATION ===
  function animateProgressBars() {
    document.querySelectorAll(".progress-fill").forEach(bar => {
      const width = bar.style.width;
      bar.style.width = "0%";
      setTimeout(() => { bar.style.width = width; }, 300);
    });
  }

  // === TAB SWITCHING INSIDE REQUESTS ===
  document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", function() {
      document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
      this.classList.add("active");
      document.querySelectorAll(".request-section").forEach(sec => sec.classList.remove("active"));
      const target = document.getElementById("requestSection-" + this.dataset.tab);
      if (target) target.classList.add("active");

      // Show or hide filter bar based on tab
      const filterBar = document.getElementById("activeFilters");
      if (this.dataset.tab === "active") {
        filterBar.style.display = "flex";
      } else {
        filterBar.style.display = "none";
      }
    });
  });


  // === CARD HOVER EFFECT ===
  document.querySelectorAll(".provider-card, .request-card").forEach(card => {
    card.addEventListener("mouseenter", () => { card.style.transform = "translateY(-6px)"; });
    card.addEventListener("mouseleave", () => { card.style.transform = "translateY(0)"; });
  });

  // === DASHBOARD FILTER (inside DOMContentLoaded) ===
  const filterSelect = document.getElementById("dashboardFilter");
  if (filterSelect) {
    filterSelect.addEventListener("change", async () => {
      const filter = filterSelect.value;
      try {
        const res = await fetch(`client.php?ajax=1&action=requests_over_time&filter=${filter}`);
        const chartData = await res.json();

        const ctx = document.getElementById("requestsOverTimeChart").getContext("2d");

        if (chartData.ok && chartData.data.length > 0) {
          const labels = chartData.data.map(r => r.Month);
          const counts = chartData.data.map(r => r.Count);

          if (window.clientRequestsChart) window.clientRequestsChart.destroy();

          window.clientRequestsChart = new Chart(ctx, {
            type: "line",
            data: {
              labels,
              datasets: [{
                label: "Requests Over Time",
                data: counts,
                borderColor: "#007bff",
                backgroundColor: "rgba(0,123,255,0.15)",
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
              }],
            },
            options: {
              plugins: { legend: { display: false } },
              scales: {
                y: { beginAtZero: true, title: { display: true, text: "Requests" } },
                x: { title: { display: true, text: "Month" } },
              },
            },
          });
        } else {
          ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
          ctx.font = "16px Inter, sans-serif";
          ctx.fillStyle = "#777";
          ctx.textAlign = "center";
          ctx.fillText("No request data yet", ctx.canvas.width / 2, ctx.canvas.height / 2);
        }
      } catch (err) {
        console.error("Filter load failed:", err);
      }
    });
  }

});

// ==============================
// CLIENT DASHBOARD LOAD FUNCTION
// ==============================
async function loadClientDashboard(filter = "all") {
  try {
    const base = "client.php?ajax=1";

    // --- Booking Summary ---
    const summaryRes = await fetch(`${base}&action=booking_summary`);
    const summary = await summaryRes.json();

    if (summary.ok) {
      const data = summary.data || {};
      function animateCount(id, endValue) {
      const el = document.getElementById(id);
      let start = 0;
      const duration = 1000;
      const stepTime = Math.max(Math.floor(duration / endValue), 20);
      const timer = setInterval(() => {
        start++;
        el.textContent = start;
        if (start >= endValue) clearInterval(timer);
      }, stepTime);
    }

animateCount("pendingCount", data.Pending || 0);
animateCount("inProgressCount", data["In Progress"] || data["In progress"] || 0);
animateCount("completedCount", data.Completed || 0);
       
      // === TOOLTIP TEXT UPDATE ===
        const pending = data.Pending || 0;
        const inProg = data["In Progress"] || data["In progress"] || 0;
        const completed = data.Completed || 0;

        document.querySelector("#pendingCount").closest(".stat-card")
          ?.setAttribute("data-tooltip", `You currently have ${pending} pending request${pending === 1 ? '' : 's'}.`);

        document.querySelector("#inProgressCount").closest(".stat-card")
          ?.setAttribute("data-tooltip", `You currently have ${inProg} in-progress request${inProg === 1 ? '' : 's'}.`);

        document.querySelector("#completedCount").closest(".stat-card")
          ?.setAttribute("data-tooltip", `You’ve completed ${completed} request${completed === 1 ? '' : 's'} so far.`);


    }

    // --- Requests Over Time ---
    const chartRes = await fetch(`${base}&action=requests_over_time&filter=${filter}`);
    const chartData = await chartRes.json();

    if (chartData.ok && chartData.data.length > 0) {
      const ctx = document.getElementById("requestsOverTimeChart").getContext("2d");
      const labels = chartData.data.map(r => r.Month);
      const counts = chartData.data.map(r => r.Count);

      // Destroy old chart if exists
      if (window.clientRequestsChart) window.clientRequestsChart.destroy();

      const chartCanvas = document.getElementById("requestsOverTimeChart");
      chartCanvas.style.transition = "opacity 0.3s ease";
      chartCanvas.style.opacity = "0";
      setTimeout(() => (chartCanvas.style.opacity = "1"), 150);

      window.clientRequestsChart = new Chart(ctx, {
        type: "line",
        data: {
          labels,
          datasets: [{
            label: "Requests Over Time",
            data: counts,
            borderColor: "#007bff",
            backgroundColor: "rgba(0,123,255,0.15)",
            fill: true,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 3,
          }],
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, title: { display: true, text: "Requests" } },
            x: { title: { display: true, text: "Month" } },
          },
        },
      });
    } else {
      const ctx = document.getElementById("requestsOverTimeChart").getContext("2d");
      ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
      ctx.font = "16px Inter, sans-serif";
      ctx.fillStyle = "#777";
      ctx.textAlign = "center";
      ctx.fillText("No request data yet", ctx.canvas.width / 2, ctx.canvas.height / 2);
    }


  } catch (err) {
    console.error("Dashboard load failed:", err);
  }
}


// Auto-load on page open
window.addEventListener("load", () => {
  setTimeout(() => loadClientDashboard(), 300);
  const filterSelect = document.getElementById("dashboardFilter");
  if (filterSelect) {
    filterSelect.addEventListener("change", () => {
      const selectedFilter = filterSelect.value;
      loadClientDashboard(selectedFilter);
    });
  }
});
window.addEventListener("load", () => {
  const activeTab = document.querySelector(".tab-btn.active");
  const filterBar = document.getElementById("activeFilters");
  if (activeTab && activeTab.dataset.tab === "active") {
    filterBar.style.display = "flex";
  }
});
