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

    // Save active section
    localStorage.setItem("activeSection", sectionId);

    // Scroll adjust
    setTimeout(() => {
      const section = document.getElementById(sectionId);
      const h2Element = section.querySelector('h2');
      const navbarHeight = document.querySelector('.top-nav').offsetHeight || 80;
      const h2Top = h2Element.offsetTop;

      window.scrollTo({
        top: h2Top - navbarHeight - 20,
        behavior: 'smooth'
      });
    }, 100);
  }

  document.getElementById("browseLink").addEventListener("click", e => {
    e.preventDefault();
    showSection("browseSection");
  });

  document.getElementById("requestsLink").addEventListener("click", e => {
    e.preventDefault();
    showSection("requestSection");
  });

  // Load section
  const urlParams = new URLSearchParams(window.location.search);
  const sectionFromUrl = urlParams.get("section");
  const savedSection = sectionFromUrl || localStorage.getItem("activeSection") || "browseSection";
  showSection(savedSection);

  // === Modal Helpers ===
  function openModal(id) {
    document.getElementById(id).classList.add("show");
    document.body.style.overflow = 'hidden';
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove("show");
    document.body.style.overflow = 'auto';
  }

  document.querySelectorAll(".close-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      btn.closest(".modal").classList.remove("show");
      document.body.style.overflow = 'auto';
    });
  });

  window.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) {
      e.target.classList.remove("show");
      document.body.style.overflow = 'auto';
    }
  });

  // === Read More ===
  const fullDescription = document.getElementById("fullDescription");
  document.querySelectorAll(".read-more-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      fullDescription.innerHTML = btn.getAttribute("data-description").replace(/\n/g, "<br>");
      openModal("descModal");
    });
  });

  // === Contact Provider ===
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

  // === Progress Bars ===
  function animateProgressBars() {
    document.querySelectorAll('.progress-fill').forEach(bar => {
      const width = bar.style.width;
      bar.style.width = '0%';
      setTimeout(() => { bar.style.width = width; }, 300);
    });
  }
  document.getElementById("requestsLink").addEventListener("click", () => {
    setTimeout(animateProgressBars, 500);
  });
  if (savedSection === "requestSection") {
    setTimeout(animateProgressBars, 500);
  }

  // === Escape closes modal ===
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.show').forEach(modal => {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
      });
    }
  });

  // === Toast Notifications ===
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

  // === Booking Fix ===
  document.querySelectorAll(".book-form").forEach(form => {
    form.addEventListener("submit", function (e) {
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

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ajax-book-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = form.querySelector('.book-btn');
        btn.disabled = true;
        btn.textContent = 'Booking...';

        const skillId = form.querySelector('input[name="book_skill_id"]').value;

        fetch('book_skill.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'skill_id=' + encodeURIComponent(skillId)
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            btn.textContent = 'Booked!';
            btn.classList.add('booked');
            setTimeout(() => {
              // Optionally, refresh requests section or show a toast
              window.location.reload(); // Or use AJAX to update requests
            }, 1000);
          } else {
            btn.textContent = 'Book Now';
            btn.disabled = false;
            alert(data.message || 'Booking failed.');
          }
        })
        .catch(() => {
          btn.textContent = 'Book Now';
          btn.disabled = false;
          alert('Network error.');
        });
      });
    });
  });

  // === Hover effects ===
  document.querySelectorAll('.provider-card, .request-card').forEach(card => {
    card.addEventListener('mouseenter', () => { card.style.transform = 'translateY(-8px)'; });
    card.addEventListener('mouseleave', () => { card.style.transform = 'translateY(-6px)'; });
  });

  document.querySelectorAll('.cancel-request-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      if (!confirm('Are you sure you want to cancel this request?')) return;
      const requestId = btn.getAttribute('data-request-id');
      btn.disabled = true;
      btn.textContent = 'Cancelling...';
      fetch('cancel_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'request_id=' + encodeURIComponent(requestId)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          btn.textContent = 'Cancelled';
          btn.classList.add('cancelled');
          setTimeout(() => window.location.reload(), 1000);
        } else {
          btn.disabled = false;
          btn.textContent = 'Cancel Request';
          alert(data.message || 'Cancel failed.');
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Cancel Request';
        alert('Network error.');
      });
    });
  });
});
