// ============================================
// FLATPICKR CALENDAR INITIALIZATION
// ============================================
function initializeDatePickers() {
  const dateInputs = document.querySelectorAll('.flatpickr-input');
  
  dateInputs.forEach(input => {
    if (input._flatpickr) return; // Already initialized
    
    const form = input.closest('form');
    const hiddenInput = form?.querySelector('input[name="preferred_schedule"]');
    const skillIdInput = form?.querySelector('input[name="book_skill_id"]');
    const providerId = skillIdInput?.value;
    
    flatpickr(input, {
      enableTime: true,
      dateFormat: "F j, Y at h:i K",
      altInput: true,
      altFormat: "F j, Y at h:i K",
      minDate: "today",
      time_24hr: false,
      minuteIncrement: 15,
      onChange: function(selectedDates, dateStr, instance) {
        // Convert to Y-m-d\TH:i format for backend
        if (selectedDates[0] && hiddenInput) {
          const date = selectedDates[0];
          const year = date.getFullYear();
          const month = String(date.getMonth() + 1).padStart(2, '0');
          const day = String(date.getDate()).padStart(2, '0');
          const hours = String(date.getHours()).padStart(2, '0');
          const minutes = String(date.getMinutes()).padStart(2, '0');
          hiddenInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }
      },
      onReady: async function(selectedDates, dateStr, instance) {
        // Fetch provider availability
        if (!providerId) return;
        
        try {
          const res = await fetch(`provider.php?ajax=1&action=get_availability&provider=${providerId}`);
          const data = await res.json();
          
          if (data.ok && data.data.length > 0) {
            const availability = data.data;
            
            // Disable dates where provider is NOT available
            instance.set('disable', [
              function(date) {
                const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
                const hours = date.getHours();
                const minutes = date.getMinutes();
                const timeInMinutes = hours * 60 + minutes;
                
                // Check if this day+time is available
                const isAvailable = availability.some(slot => {
                  if (slot.DayOfWeek !== dayName) return false;
                  
                  // Convert slot times to minutes
                  const [startH, startM] = slot.StartTime.split(':').map(Number);
                  const [endH, endM] = slot.EndTime.split(':').map(Number);
                  const startMinutes = startH * 60 + startM;
                  const endMinutes = endH * 60 + endM;
                  
                  return timeInMinutes >= startMinutes && timeInMinutes <= endMinutes;
                });
                
                return !isAvailable; // Disable if NOT available
              }
            ]);
            
            console.log('Provider availability loaded:', availability);
          }
        } catch (err) {
          console.error('Failed to load availability:', err);
        }
      }
    });
  });
}

// ==========================
// CLIENT DASHBOARD JAVASCRIPT
// ==========================
document.addEventListener("DOMContentLoaded", () => {
  
  // Initialize date pickers on load
  setTimeout(() => initializeDatePickers(), 500);

  // === SECTION NAVIGATION ===
  function showSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) {
        console.warn(`Section ${sectionId} not found`);
        return;
    }

    // Hide all sections
    document.querySelectorAll("main section").forEach(sec => {
        sec.classList.remove("active");
    });

    // Show target
    section.classList.add("active");

    // Highlight nav
    document.querySelectorAll(".nav-links a").forEach(a => a.classList.remove("active"));
    const linkMap = {
        "dashboardSection": "dashboardLink",
        "browseSection": "browseLink",
        "requestSection": "requestsLink"
    };
    const linkId = linkMap[sectionId];
    if (linkId) {
        const link = document.getElementById(linkId);
        if (link) link.classList.add("active");
    }

    // Save active section
    sessionStorage.setItem("activeSection", sectionId);

    // Default to Active tab in Requests
    if (sectionId === "requestSection") {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        document.querySelectorAll(".request-section").forEach(sec => sec.classList.remove("active"));
        const activeTab = document.querySelector('.tab-btn[data-tab="active"]');
        const activeSection = document.getElementById("requestSection-active");
        if (activeTab) activeTab.classList.add("active");
        if (activeSection) activeSection.classList.add("active");
    }

    // Re-init date pickers when switching sections
    if (sectionId === "browseSection") {
      setTimeout(() => initializeDatePickers(), 300);
    }

    // Smooth scroll
    setTimeout(() => {
        const h2Element = section.querySelector("h2");
        if (!h2Element) return;
        const navbarHeight = document.querySelector(".top-nav")?.offsetHeight || 80;
        const h2Top = h2Element.getBoundingClientRect().top + window.pageYOffset;
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
  const savedSection = sectionFromUrl || sessionStorage.getItem("activeSection") || "dashboardSection";
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

  // === CANCEL REQUEST ===
  document.querySelectorAll(".cancel-request-btn").forEach(btn => {
    btn.addEventListener("click", function() {
      if (!confirm("Are you sure you want to cancel this request?")) return;
      const requestId = btn.getAttribute("data-request-id");
      btn.disabled = true;
      btn.textContent = "Cancelling...";

      fetch("client.php?ajax=1&action=cancel_request", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "request_id=" + encodeURIComponent(requestId)
      })
        .then(res => res.json())
        .then(data => {
          if (data.ok) {
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
    // === BOOK AGAIN FUNCTIONALITY ===
  document.querySelectorAll(".book-again-btn").forEach(btn => {
    btn.addEventListener("click", function() {
      const skillId = this.getAttribute("data-skill-id");
      const providerName = this.getAttribute("data-provider");
      
      if (confirm(`Book ${providerName} again?`)) {
        document.getElementById('browseLink').click();
        
        setTimeout(() => {
          const cards = document.querySelectorAll('.provider-card');
          cards.forEach(card => {
            const bookForm = card.querySelector('form[method="POST"]');
            if (bookForm) {
              const skillInput = bookForm.querySelector('input[name="book_skill_id"]');
              if (skillInput && skillInput.value === skillId) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.style.border = '3px solid #28a745';
                card.style.boxShadow = '0 0 30px rgba(40, 167, 69, 0.4)';
                
                setTimeout(() => {
                  card.style.border = '';
                  card.style.boxShadow = '';
                }, 3000);
              }
            }
          });
        }, 500);
      }
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

      const filterBar = document.getElementById("activeFilters");
      if (this.dataset.tab === "active") {
        filterBar.classList.add("active");
      } else {
        filterBar.classList.remove("active");
      }
    });
  });

  // === CARD HOVER EFFECT ===
  document.querySelectorAll(".provider-card, .request-card").forEach(card => {
    card.addEventListener("mouseenter", () => { card.style.transform = "translateY(-6px)"; });
    card.addEventListener("mouseleave", () => { card.style.transform = "translateY(0)"; });
  });

  

  // === DASHBOARD FILTER ===
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
      animateCount("inProgressCount", data["In Progress"] || 0);
      animateCount("completedCount", data.Completed || 0);
    }

    const chartRes = await fetch(`${base}&action=requests_over_time&filter=${filter}`);
    const chartData = await chartRes.json();

    if (chartData.ok && chartData.data.length > 0) {
      const ctx = document.getElementById("requestsOverTimeChart").getContext("2d");
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
    }
  } catch (err) {
    console.error("Dashboard load failed:", err);
  }
}

window.addEventListener("load", () => {
  setTimeout(() => loadClientDashboard(), 300);

  const filterSelect = document.getElementById("dashboardFilter");
  if (filterSelect) {
    filterSelect.addEventListener("change", () => {
      loadClientDashboard(filterSelect.value);
    });
  }

  setTimeout(() => {
    document.querySelectorAll('.success-message, .error-message').forEach(msg => {
      msg.style.transition = 'opacity 0.5s ease';
      msg.style.opacity = '0';
      setTimeout(() => msg.remove(), 500);
    });
  }, 4000);
});

// ============================================
// PROVIDER SPOTLIGHT FUNCTIONS
// ============================================

function bookAgain(skillId, providerName) {
  if (confirm(`Book ${providerName} again?`)) {
    document.getElementById('browseLink').click();
    
    setTimeout(() => {
      const cards = document.querySelectorAll('.provider-card');
      cards.forEach(card => {
        const bookForm = card.querySelector('form[method="POST"]');
        if (bookForm) {
          const skillInput = bookForm.querySelector('input[name="book_skill_id"]');
          if (skillInput && parseInt(skillInput.value) === skillId) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.style.border = '3px solid #28a745';
            card.style.boxShadow = '0 0 30px rgba(40, 167, 69, 0.4)';
            setTimeout(() => {
              card.style.border = '';
              card.style.boxShadow = '';
            }, 3000);
          }
        }
      });
    }, 500);
  }
}

function viewProviderProfile(providerId, name, skill, rate, bookingCount) {
  const info = document.getElementById('contactInfo');
  
  info.innerHTML = `
    <div style="text-align: center; margin-bottom: 1.5rem;">
      <div style="width: 80px; height: 80px; margin: 0 auto 1rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
        ${name.split(' ').map(n => n[0]).join('').toUpperCase()}
      </div>
      <h3 style="margin: 0 0 0.5rem 0; color: #2c3e50;">${name}</h3>
      <p style="color: #667eea; font-weight: 600; margin: 0;">${skill}</p>
    </div>
    
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
        <div style="text-align: center; flex: 1;">
          <div style="font-size: 1.5rem; font-weight: 800; color: #667eea;">${bookingCount}</div>
          <div style="font-size: 0.85rem; color: #6c757d;">Bookings</div>
        </div>
        <div style="width: 1px; background: #dee2e6;"></div>
        <div style="text-align: center; flex: 1;">
          <div style="font-size: 1.5rem; font-weight: 800; color: #28a745;">₱${rate}</div>
          <div style="font-size: 0.85rem; color: #6c757d;">Per Hour</div>
        </div>
      </div>
    </div>
    
    <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; border-left: 4px solid #ffc107;">
      <p style="margin: 0; color: #856404; font-weight: 600;">
        💡 Direct messaging and detailed profiles coming soon!
      </p>
    </div>
  `;
  
  openModal('contactModal');
}