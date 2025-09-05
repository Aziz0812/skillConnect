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

    // Scroll to top of section
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

  // Default to browse section
  showSection("browseSection");

  // === Modal Helpers ===
  function openModal(id) {
    document.getElementById(id).classList.add("show");
    document.body.style.overflow = 'hidden'; // Prevent background scroll
  }
  
  function closeModal(id) {
    document.getElementById(id).classList.remove("show");
    document.body.style.overflow = 'auto'; // Restore scroll
  }

  // Close buttons
  document.querySelectorAll(".close-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      btn.closest(".modal").classList.remove("show");
      document.body.style.overflow = 'auto';
    });
  });

  // Click outside to close
  window.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) {
      e.target.classList.remove("show");
      document.body.style.overflow = 'auto';
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

  // === Contact Provider Handler ===
  const contactModal = document.getElementById("contactModal");
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

  // === Progress Bar Animation ===
  function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
      const width = bar.style.width;
      bar.style.width = '0%';
      setTimeout(() => {
        bar.style.width = width;
      }, 300);
    });
  }

  // Animate progress bars when requests section is shown
  document.getElementById("requestsLink").addEventListener("click", () => {
    setTimeout(animateProgressBars, 500);
  });

  // === Auto-hide Success Messages ===
  const successMessages = document.querySelectorAll('.success-message');
  successMessages.forEach(msg => {
    setTimeout(() => {
      msg.style.transition = 'opacity 0.5s ease';
      msg.style.opacity = '0';
      setTimeout(() => {
        msg.style.display = 'none';
      }, 500);
    }, 3000);
  });

  // === Keyboard Navigation ===
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.show').forEach(modal => {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
      });
    }
  });

  // === Enhanced Interactions ===
  // Add loading state to book buttons
  document.querySelectorAll('form button[type="submit"]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.innerHTML = 'Booking...';
      btn.disabled = true;
    });
  });

  // Add hover effects to cards
  document.querySelectorAll('.provider-card, .request-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
      card.style.transform = 'translateY(-8px)';
    });
    
    card.addEventListener('mouseleave', () => {
      card.style.transform = 'translateY(-6px)';
    });
  });
});