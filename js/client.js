// ============================================
// FLATPICKR CALENDAR INITIALIZATION
// ============================================
function initializeDatePickers() {
  const dateInputs = document.querySelectorAll('.flatpickr-input');
  
  dateInputs.forEach(input => {
    if (input._flatpickr) return; // Already initialized
    
    const form = input.closest('form');
    const hiddenInput = form?.querySelector('input[name="preferred_schedule"]');
    const providerIdInput = form?.querySelector('input[name="provider_id"]');
    const skillIdInput = form?.querySelector('input[name="book_skill_id"]');
    const providerId = providerIdInput?.value || skillIdInput?.value || '';
    
    // Cache availability per input instance
    let cachedAvailability = null;

    // Ensure a hint container exists under the input
    let hint = form?.querySelector('.schedule-hint');
    if (!hint) {
      hint = document.createElement('div');
      hint.className = 'schedule-hint';
      hint.style.fontSize = '0.85rem';
      hint.style.marginTop = '6px';
      hint.style.color = '#6c757d';
      input.parentElement?.appendChild(hint);
    }
    // Ensure a pills container exists under the hint
    let pills = form?.querySelector('.availability-pills');
    if (!pills) {
      pills = document.createElement('div');
      pills.className = 'availability-pills';
      if (hint && hint.parentElement) {
        hint.parentElement.appendChild(pills);
      } else {
        input.parentElement?.appendChild(pills);
      }
    }

    const fp = flatpickr(input, {
      enableTime: true,
      dateFormat: "F j, Y at h:i K",
      altInput: true,
      altFormat: "F j, Y at h:i K",
      minDate: "today",
      time_24hr: false,
      minuteIncrement: 15,
      onChange: function(selectedDates, dateStr, instance) {
        if (!selectedDates[0]) return;

        // reset hint state
        if (hint) { hint.classList.remove('error'); hint.style.color = '#6c757d'; }

        const date = selectedDates[0];
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const isoVal = `${year}-${month}-${day}T${hours}:${minutes}`;

        // Validate against availability if present
        if (cachedAvailability && Array.isArray(cachedAvailability) && cachedAvailability.length > 0) {
          const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });
          const totalMinutes = parseInt(hours, 10) * 60 + parseInt(minutes, 10);
          const isValid = cachedAvailability.some(slot => {
            if (slot.DayOfWeek !== weekday) return false;
            const [sh, sm] = slot.StartTime.split(':').map(Number);
            const [eh, em] = slot.EndTime.split(':').map(Number);
            const startM = sh * 60 + sm;
            const endM = eh * 60 + em;
            return totalMinutes >= startM && totalMinutes <= endM;
          });
          if (!isValid) {
            // Clear and inline notify
            if (hiddenInput) hiddenInput.value = '';
            instance.clear();
            if (hint) {
              const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });
              const ranges = cachedAvailability
                .filter(s => s.DayOfWeek === weekday)
                .map(s => `${s.StartTime.substring(0,5)}–${s.EndTime.substring(0,5)}`)
                .join(', ');
              hint.textContent = ranges ? `Outside provider hours for ${weekday} (${ranges}).` : `Provider is unavailable on ${weekday}.`;
              hint.classList.add('error');
              hint.style.color = '#dc3545';
            }
            return;
          }
        }

        // Set hidden input for backend
        if (hiddenInput) hiddenInput.value = isoVal;
        if (hint) {
          const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });
          const ranges = cachedAvailability && Array.isArray(cachedAvailability)
            ? cachedAvailability.filter(s => s.DayOfWeek === weekday).map(s => `${s.StartTime.substring(0,5)}–${s.EndTime.substring(0,5)}`).join(', ')
            : '';
          hint.textContent = ranges ? `Available on ${weekday}: ${ranges}` : '';
          hint.classList.remove('error');
          hint.style.color = '#198754';
        }
      },
      onReady: async function(selectedDates, dateStr, instance) {
        // Fetch provider availability and disable days not offered by provider
        if (hint) hint.textContent = 'Fetching provider availability...';
        if (!providerId) { if (hint) hint.textContent = ''; return; }
        try {
          const res = await fetch(`provider.php?ajax=1&action=get_availability&provider=${providerId}`);
          const data = await res.json();
          if (data.ok && Array.isArray(data.data) && data.data.length > 0) {
            cachedAvailability = data.data;
            const availableDays = new Set(cachedAvailability.map(s => s.DayOfWeek));
            instance.set('disable', [
              function(date) {
                const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
                return !availableDays.has(dayName);
              }
            ]);

            // Decorate disabled days with tooltip
            instance.set('onDayCreate', [function(dObj, dStr, fp, dayElem) {
              const dayName = dayElem.dateObj.toLocaleDateString('en-US', { weekday: 'long' });
              if (!availableDays.has(dayName)) {
                dayElem.classList.add('unavailable-day');
                dayElem.setAttribute('title', `Unavailable: Provider does not work on ${dayName}`);
              }
            }]);

            // Render availability summary and pills
            const order = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            if (hint) {
              const byDay = order.map(day => ({ day, slots: cachedAvailability.filter(s => s.DayOfWeek === day) }));
              const working = byDay.filter(d => d.slots.length > 0);
              const off = byDay.filter(d => d.slots.length === 0).map(d => d.day);
              const ranges = working.map(d => `${d.day.substring(0,3)}: ${d.slots.map(s => s.StartTime.substring(0,5)+"–"+s.EndTime.substring(0,5)).join(' | ')}`).join('  •  ');
              hint.textContent = ranges ? `Availability • ${ranges}${off.length ? `  •  Off: ${off.join(', ')}` : ''}` : 'Availability not provided';
              hint.style.color = '#6c757d';
              hint.classList.remove('error');
            }
            if (pills) {
              pills.innerHTML = order
                .map(d => `<span class="pill ${availableDays.has(d) ? 'on' : 'off'}">${d.substring(0,3)}</span>`)
                .join('');
            }
          } else {
            if (hint) { hint.textContent = 'Availability not provided'; hint.style.color = '#6c757d'; }
          }
        } catch (err) {
          console.error('Failed to load availability:', err);
          if (hint) { hint.textContent = 'Failed to load availability'; hint.style.color = '#dc3545'; }
        }
      }
    });

    // Prevent form submission if invalid or empty schedule
    if (form) {
      form.addEventListener('submit', (e) => {
        // if hidden input missing or empty, block
        if (!hiddenInput || !hiddenInput.value) {
          e.preventDefault();
          // Inline fading error (no showToast dependency)
          let hintLocal = form.querySelector('.schedule-hint');
          if (!hintLocal) {
            hintLocal = document.createElement('div');
            hintLocal.className = 'schedule-hint error';
            if (input && input.parentElement) input.parentElement.appendChild(hintLocal);
            else form.appendChild(hintLocal);
          }
          hintLocal.classList.add('error');
          hintLocal.style.color = '#991b1b';
          hintLocal.textContent = 'Please set a date and time before booking.';
          // auto dismiss after 3s with smooth fade
          hintLocal.style.transition = 'opacity 0.5s ease';
          setTimeout(() => {
            hintLocal.style.opacity = '0';
            setTimeout(() => { hintLocal.remove(); }, 500);
          }, 3000);
          if (input) input.focus();
          return;
        }
        // If we have availability, validate again on submit as a safety net
        if (cachedAvailability && Array.isArray(cachedAvailability) && cachedAvailability.length > 0) {
          const val = hiddenInput.value; // format YYYY-MM-DDTHH:mm
          const [d, t] = val.split('T');
          if (!d || !t) {
            e.preventDefault();
            // inline fading error
            let hintLocal = form.querySelector('.schedule-hint');
            if (!hintLocal) {
              hintLocal = document.createElement('div');
              hintLocal.className = 'schedule-hint error';
              if (input && input.parentElement) input.parentElement.appendChild(hintLocal);
              else form.appendChild(hintLocal);
            }
            hintLocal.classList.add('error');
            hintLocal.style.color = '#991b1b';
            hintLocal.textContent = 'Please set a valid date and time before booking.';
            hintLocal.style.transition = 'opacity 0.5s ease';
            setTimeout(() => { hintLocal.style.opacity = '0'; setTimeout(() => hintLocal.remove(), 500); }, 3000);
            return;
          }
          const [yy, mm, dd] = d.split('-').map(Number);
          const [HH, MM] = t.split(':').map(Number);
          const dt = new Date(yy, mm - 1, dd, HH, MM);
          const weekday = dt.toLocaleDateString('en-US', { weekday: 'long' });
          const totalMinutes = HH * 60 + MM;
          const isValid = cachedAvailability.some(slot => {
            if (slot.DayOfWeek !== weekday) return false;
            const [sh, sm] = slot.StartTime.split(':').map(Number);
            const [eh, em] = slot.EndTime.split(':').map(Number);
            const startM = sh * 60 + sm;
            const endM = eh * 60 + em;
            return totalMinutes >= startM && totalMinutes <= endM;
          });
          if (!isValid) {
            e.preventDefault();
            let hintLocal = form.querySelector('.schedule-hint');
            if (!hintLocal) {
              hintLocal = document.createElement('div');
              hintLocal.className = 'schedule-hint error';
              if (input && input.parentElement) input.parentElement.appendChild(hintLocal);
              else form.appendChild(hintLocal);
            }
            // find weekday ranges for message
            const dtWeekday = new Date(yy, mm - 1, dd).toLocaleDateString('en-US', { weekday: 'long' });
            const ranges = cachedAvailability
              .filter(s => s.DayOfWeek === dtWeekday)
              .map(s => `${s.StartTime.substring(0,5)}–${s.EndTime.substring(0,5)}`)
              .join(', ');
            hintLocal.textContent = ranges ? `Outside provider hours for ${dtWeekday} (${ranges}).` : `Provider is unavailable on ${dtWeekday}.`;
            hintLocal.classList.add('error');
            hintLocal.style.color = '#991b1b';
            hintLocal.style.transition = 'opacity 0.5s ease';
            setTimeout(() => { hintLocal.style.opacity = '0'; setTimeout(() => hintLocal.remove(), 500); }, 3000);
            return;
          }
        }
      });
    }
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
    // ⚠️ NORMALIZE SECTION ID (handle both "browse" and "browseSection")
    let normalizedId = sectionId;
    if (!sectionId.endsWith('Section')) {
      normalizedId = sectionId + 'Section';
    }
    
    const section = document.getElementById(normalizedId);
    if (!section) {
        console.warn(`Section ${normalizedId} not found`);
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
    const linkId = linkMap[normalizedId];
    if (linkId) {
        const link = document.getElementById(linkId);
        if (link) link.classList.add("active");
    }

    // Save active section
    sessionStorage.setItem("activeSection", normalizedId);

    // Default to Active tab in Requests
    if (normalizedId === "requestSection") {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        document.querySelectorAll(".request-section").forEach(sec => sec.classList.remove("active"));
        const activeTab = document.querySelector('.tab-btn[data-tab="active"]');
        const activeSection = document.getElementById("requestSection-active");
        if (activeTab) activeTab.classList.add("active");
        if (activeSection) activeSection.classList.add("active");
    }

    // Re-init date pickers when switching sections
    if (normalizedId === "browseSection") {
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
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add("show");
    document.body.style.overflow = "hidden";
  }
  function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove("show");
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
      if (!fullDescription) return;
      const desc = btn.getAttribute("data-description") || "";
      fullDescription.innerHTML = desc.replace(/\n/g, "<br>");
      openModal("descModal");
    });
  });

  // === CONTACT PROVIDER MODAL ===
  const contactInfo = document.getElementById("contactInfo");
  document.querySelectorAll(".contact-provider-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      if (!contactInfo) return;
      const providerName = btn.getAttribute("data-provider") || "";
      const serviceName = btn.getAttribute("data-service") || "";
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
      e.preventDefault(); // ⚠️ CRITICAL: Prevent default form submission
      
      const hidden = form.querySelector('input[name="preferred_schedule"]');
      const input = form.querySelector('.flatpickr-input');
      
      // Validate schedule is selected
      if (!hidden || !hidden.value) {
        if (input) input.focus();
        let hint = form.querySelector('.schedule-hint');
        if (!hint) {
          hint = document.createElement('div');
          hint.className = 'schedule-hint error';
          if (input && input.parentElement) input.parentElement.appendChild(hint);
          else form.appendChild(hint);
        }
        hint.classList.add('error');
        hint.style.color = '#991b1b';
        hint.textContent = 'Please set a date and time before booking.';
        return;
      }
      
      // Valid: apply animation
      const card = this.closest(".provider-card");
      if (card) {
        card.style.transition = "opacity 0.4s ease, transform 0.4s ease";
        card.style.opacity = "0.6";
        card.style.transform = "translateY(4px)";
      }
      
      // Get form data
      const formData = new FormData(this);
      const skillId = formData.get('book_skill_id');
      const schedule = formData.get('preferred_schedule');
      
      // Submit via AJAX
      fetch('book_skill.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `skill_id=${encodeURIComponent(skillId)}&preferred_schedule=${encodeURIComponent(schedule)}`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Success - redirect to requests section
          window.location.href = 'client.php?section=requestSection&success=1';
        } else if (data.error === 'already_booked') {
          // Already have active booking
          showToast(data.message || 'You already have an active booking for this service.', 'error');
          if (card) {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
          }
        } else {
          // Other error
          showToast(data.message || 'Booking failed. Please try again.', 'error');
          if (card) {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
          }
        }
      })
      .catch(err => {
        console.error('Booking error:', err);
        showToast('Network error. Please try again.', 'error');
        if (card) {
          card.style.opacity = "1";
          card.style.transform = "translateY(0)";
        }
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
      const providerId = this.getAttribute("data-provider-id");
      
      // Set modal title
      const titleEl = document.getElementById('bookAgainTitle');
      if (titleEl) titleEl.textContent = `Book ${providerName || ''} Again`;
      
      // Set hidden skill ID
      const rebookSkillIdEl = document.getElementById('rebookSkillId');
      if (rebookSkillIdEl) rebookSkillIdEl.value = skillId || '';
      
      // Initialize flatpickr for rebook
      const rebookPicker = document.getElementById('rebookDatePicker');
      if (!rebookPicker) { openModal('bookAgainModal'); return; }
      if (rebookPicker._flatpickr) {
        rebookPicker._flatpickr.destroy();
      }
      
      flatpickr(rebookPicker, {
        enableTime: true,
        dateFormat: "F j, Y at h:i K",
        altInput: true,
        altFormat: "F j, Y at h:i K",
        minDate: "today",
        time_24hr: false,
        minuteIncrement: 15,
        onChange: function(selectedDates, dateStr, instance) {
          if (selectedDates[0]) {
            const date = selectedDates[0];
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            document.getElementById('rebookScheduleHidden').value = `${year}-${month}-${day}T${hours}:${minutes}`;
          }
        },
        onReady: async function(selectedDates, dateStr, instance) {
          // Fetch provider availability if providerId exists and disable days not offered
          if (!providerId) return;
          try {
            const res = await fetch(`provider.php?ajax=1&action=get_availability&provider=${providerId}`);
            const data = await res.json();
            if (data.ok && Array.isArray(data.data) && data.data.length > 0) {
              const availability = data.data;
              const availableDays = new Set(availability.map(s => s.DayOfWeek));
              instance.set('disable', [
                function(date) {
                  const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
                  return !availableDays.has(dayName);
                }
              ]);
            }
          } catch (err) {
            console.error('Failed to load availability:', err);
          }
        }
      });
      
      // Open modal
      openModal('bookAgainModal');
    });
  });

  // === PROGRESS BAR ANIMATION ===
  function animateProgressBars() {
    document.querySelectorAll(".progress-fill").forEach(bar => {
      const width = bar.style.width || getComputedStyle(bar).width;
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

        const canvas = document.getElementById("requestsOverTimeChart");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");

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
        if (!el) return;
        const safeEnd = Number.isFinite(endValue) && endValue > 0 ? Math.floor(endValue) : 0;
        if (safeEnd === 0) { el.textContent = 0; return; }
        let start = 0;
        const duration = 1000;
        const stepTime = Math.max(Math.floor(duration / safeEnd), 20);
        const timer = setInterval(() => {
          start++;
          el.textContent = start;
          if (start >= safeEnd) clearInterval(timer);
        }, stepTime);
      }

      animateCount("pendingCount", data.Pending || 0);
      animateCount("inProgressCount", data["In Progress"] || 0);
      animateCount("completedCount", data.Completed || 0);
    }

    const chartRes = await fetch(`${base}&action=requests_over_time&filter=${filter}`);
    const chartData = await chartRes.json();

    if (chartData.ok && chartData.data.length > 0) {
      const canvas = document.getElementById("requestsOverTimeChart");
      if (!canvas) return;
      const ctx = canvas.getContext("2d");
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

  // Cleanup only toasts inside the toast container to avoid removing unrelated elements
  setTimeout(() => {
    const container = document.querySelector('.toast-container');
    if (!container) return;
    container.querySelectorAll('.success-message, .error-message').forEach(msg => {
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