    // ============================
    // BROWSE SERVICES JS MODULE
    // ============================
document.addEventListener("DOMContentLoaded", () => {

        const browseSection = document.getElementById("browseSection");
        if (!browseSection) return; // Exit if not on client.php

        console.log("✅ browse.js loaded for Browse Services");

        // Wait until Browse section becomes active before initializing
        const observer = new MutationObserver(() => {
            if (browseSection.classList.contains("active")) {
            initBrowseFeatures();
            }
        });

        observer.observe(browseSection, { attributes: true, attributeFilter: ["class"] });

        // ----------------------------
        // Main feature initialization
        // ----------------------------
        function initBrowseFeatures() {
            console.log("🎯 Initializing Browse section features...");

            const searchBox = document.getElementById("searchBox");
            const categoryFilter = document.getElementById("categoryFilter");
            const providerCards = document.querySelectorAll("#browseSection .provider-card");

            if (!searchBox || !categoryFilter || providerCards.length === 0) {
            console.warn("⚠️ Missing browse elements or no providers found.");
            return;
            }

            function filterServices() {
            const searchTerm = searchBox.value.toLowerCase().trim();
            const selectedCategory = categoryFilter.value.toLowerCase();

            let visibleCount = 0;

            providerCards.forEach(card => {
                const name = card.querySelector(".provider-header h3")?.textContent.toLowerCase() || "";
                const category = card.querySelector(".category-badge")?.textContent.toLowerCase() || "";
                const description = card.querySelector(".card-description")?.textContent.toLowerCase() || "";
                const locationText = card.querySelector(".provider-details p")?.textContent.toLowerCase() || "";

                // 🔹 Optional: if your card has data attributes like data-city or data-province, include them
                const city = card.dataset.city?.toLowerCase() || "";
                const province = card.dataset.province?.toLowerCase() || "";
                const barangay = card.dataset.barangay?.toLowerCase() || "";

                // 🔹 Combine all searchable fields
                const searchableText = [name, category, description, locationText, city, province, barangay].join(" ");

                const matchesSearch = searchTerm === "" || searchableText.includes(searchTerm);
                const matchesCategory = selectedCategory === "all" || category.includes(selectedCategory);

                const isVisible = matchesSearch && matchesCategory;
                card.style.display = isVisible ? "block" : "none";

                if (isVisible) visibleCount++;
            });

            // Handle "No results"
            const noResults = document.getElementById("noResultsMessage");
            if (noResults) noResults.style.display = visibleCount === 0 ? "block" : "none";
            }


            // Attach event listeners
            searchBox.addEventListener("input", filterServices);
            categoryFilter.addEventListener("change", filterServices);

            // Add a "No results" message if it doesn’t exist
            if (!document.getElementById("noResultsMessage")) {
            const message = document.createElement("p");
            message.id = "noResultsMessage";
            message.textContent = "No matching services found.";
            message.style.display = "none";
            message.style.textAlign = "center";
            message.style.color = "#888";
            browseSection.appendChild(message);
            }

            console.log("✅ Browse filtering activated.");
        }






        
});
