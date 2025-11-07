    // ==========================
    // REQUEST FILTERING + TAB SWITCHING
    // (for My Requests section) with Tab Memory
    // ==========================

    document.addEventListener("DOMContentLoaded", () => {
    const tabButtons = document.querySelectorAll(".tab-btn");
    const sections = document.querySelectorAll(".request-section");
    const filterBar = document.querySelector(".filter-bar");

    // Restore last opened tab from localStorage
    const lastTab = sessionStorage.getItem("lastRequestTab") || "active";
    const defaultTab = document.querySelector(`.tab-btn[data-tab="${lastTab}"]`);
    if (defaultTab) defaultTab.click();

    tabButtons.forEach(btn => {
        btn.addEventListener("click", function () {
        // Update active tab visuals
        tabButtons.forEach(b => b.classList.remove("active"));
        this.classList.add("active");

        // Switch section visibility
        sections.forEach(sec => sec.classList.remove("active"));
        const target = document.getElementById(`requestSection-${this.dataset.tab}`);
        if (target) target.classList.add("active");

        // Save active tab to localStorage
        sessionStorage.setItem("lastRequestTab", this.dataset.tab);

        // Show filters only for Active tab
        if (filterBar) {
            filterBar.style.display = this.dataset.tab === "active" ? "flex" : "none";
        }
        });
    });

    // --- FILTERING (Active Tab Only) ---
    const searchInput = document.getElementById("requestSearch");
    const statusFilter = document.getElementById("statusFilter");
    const activeSection = document.getElementById("requestSection-active");
    if (!activeSection) return;
    const cards = activeSection.querySelectorAll(".request-card");

    // No results message
    const noResultsMsg = document.createElement("div");
    noResultsMsg.className = "empty-state";
    noResultsMsg.textContent = "No matching requests found.";
    noResultsMsg.style.display = "none";
    activeSection.appendChild(noResultsMsg);

    function filterRequests() {
        const searchTerm = (searchInput?.value || "").toLowerCase();
        const statusValue = (statusFilter?.value || "all").toLowerCase();
        let visibleCount = 0;

        cards.forEach(card => {
        const service = card.querySelector(".request-header h3")?.textContent?.toLowerCase() || "";
        const status = card.querySelector(".status-badge")?.textContent?.trim().toLowerCase() || "";
        const matchesSearch = service.includes(searchTerm);
        const matchesStatus = statusValue === "all" || status === statusValue;

        if (matchesSearch && matchesStatus) {
            card.style.display = "block";
            visibleCount++;
        } else {
            card.style.display = "none";
        }
        });

        noResultsMsg.style.display = visibleCount === 0 ? "block" : "none";
    }

    if (searchInput) searchInput.addEventListener("input", filterRequests);
    if (statusFilter) statusFilter.addEventListener("change", filterRequests);

    // Show or hide filters based on last active tab
    if (filterBar) {
        filterBar.style.display = lastTab === "active" ? "flex" : "none";
    }
    });
    // --- Handle auto-tab switch after booking success ---
    window.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);

    if (params.get("success") === "1" && params.get("section") === "request") {
        // Automatically activate the "Active" tab
        const activeTabBtn = document.querySelector('.tab-btn[data-tab="active"]');
        if (activeTabBtn) {
        activeTabBtn.click();
        }

        // Clean the URL so it doesn’t keep switching tabs after refresh
        params.delete("success");
        params.delete("section");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    const wrapper = document.querySelector('.custom-select-wrapper');
  if (!wrapper) return;
  
  const customSelect = wrapper.querySelector('.custom-select');
  const trigger = wrapper.querySelector('.custom-select__trigger');
  const options = wrapper.querySelector('.custom-options');
  const optionItems = wrapper.querySelectorAll('.custom-option');
  const hiddenSelect = document.getElementById('statusFilter');
  
  // Toggle dropdown
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    customSelect.classList.toggle('open');
  });
  
  // Handle option selection
  optionItems.forEach(option => {
    option.addEventListener('click', function(e) {
      e.stopPropagation();
      const value = this.getAttribute('data-value');
      const text = this.textContent;
      
      // Update selected styling
      optionItems.forEach(opt => opt.classList.remove('selected'));
      this.classList.add('selected');
      
      // Update trigger text
      trigger.querySelector('span').textContent = text;
      
      // Update hidden select
      hiddenSelect.value = value;
      
      // Close dropdown
      customSelect.classList.remove('open');
      
      // Trigger filter
      hiddenSelect.dispatchEvent(new Event('change'));
    });
  });
  
  // Close on outside click
  document.addEventListener('click', () => {
    customSelect.classList.remove('open');
  });
    });
