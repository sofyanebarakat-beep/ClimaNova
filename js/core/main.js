import { renderHeader } from "../components/site-header.js";
import { renderFooter } from "../components/site-footer.js";

const COMPONENTS = {
  "site-header": renderHeader,
  "site-footer": renderFooter,
};

function mountComponents() {
  document.querySelectorAll("[data-component]").forEach((target) => {
    const componentName = target.dataset.component;
    const render = COMPONENTS[componentName];

    if (render) {
      render(target);
    }
  });
}

function initBlogShareLinks() {
  const shareLinks = document.querySelectorAll("[data-share]");

  if (!shareLinks.length) {
    return;
  }

  const pageUrl = window.location.href;
  const pageTitle = document.title || "ClimaNova Energie";

  shareLinks.forEach((link) => {
    const shareType = link.dataset.share;

    if (shareType === "facebook") {
      link.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`;
      link.target = "_blank";
      link.rel = "noopener";
      return;
    }

    if (shareType === "x") {
      link.href = `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(pageTitle)}`;
      link.target = "_blank";
      link.rel = "noopener";
      return;
    }

    if (shareType === "linkedin") {
      link.href = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(pageUrl)}`;
      link.target = "_blank";
      link.rel = "noopener";
      return;
    }

    link.addEventListener("click", async (event) => {
      event.preventDefault();

      if (navigator.share) {
        await navigator.share({ title: pageTitle, url: pageUrl });
        return;
      }

      await navigator.clipboard?.writeText(pageUrl);
      link.classList.add("is-copied");
      window.setTimeout(() => link.classList.remove("is-copied"), 1400);
    });
  });
}

function initProjectCarousels() {
  const carousels = document.querySelectorAll(".cn-project-carousel");

  carousels.forEach((carousel) => {
    const slides = Array.from(carousel.children);
    const wrapper = carousel.closest(".project-slide-wrapper");
    const previousButton = wrapper?.querySelector("[data-carousel-prev]");
    const nextButton = wrapper?.querySelector("[data-carousel-next]");
    const dotsContainer = wrapper?.querySelector("[data-carousel-dots]");

    if (!slides.length || !wrapper || !previousButton || !nextButton || !dotsContainer) {
      return;
    }

    let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains("cn-anim-in")));
    let touchStartX = 0;

    carousel.setAttribute("role", "region");
    carousel.setAttribute("aria-roledescription", "carousel");
    carousel.setAttribute("aria-label", carousel.dataset.carouselLabel || "Carousel");

    const dots = slides.map((_, index) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "cn-project-carousel-dot";
      dot.setAttribute("aria-label", `Afficher la réalisation ${index + 1}`);
      dot.addEventListener("click", () => updateCarousel(index));
      dotsContainer.append(dot);
      return dot;
    });

    function getOffset(index) {
      const total = slides.length;
      let offset = index - activeIndex;

      if (offset > total / 2) {
        offset -= total;
      }

      if (offset < total / -2) {
        offset += total;
      }

      return offset;
    }

    function updateCarousel(nextIndex) {
      activeIndex = (nextIndex + slides.length) % slides.length;

      slides.forEach((slide, index) => {
        const offset = getOffset(index);
        const distance = Math.abs(offset);
        const isVisible = distance <= 3;
        const x = offset * 18;
        const z = distance * -145;
        const scale = Math.max(0.74, 1 - distance * 0.08);
        const rotate = offset * -5;

        slide.classList.toggle("is-active", offset === 0);
        slide.setAttribute("aria-hidden", offset === 0 ? "false" : "true");
        slide.style.opacity = isVisible ? String(1 - distance * 0.15) : "0";
        slide.style.pointerEvents = offset === 0 ? "auto" : "none";
        slide.style.transform = `translate3d(${x}%, 0, ${z}px) rotateY(${rotate}deg) scale(${scale})`;
        slide.style.zIndex = String(20 - distance);
      });

      dots.forEach((dot, index) => {
        const isActive = index === activeIndex;
        dot.classList.toggle("is-active", isActive);
        dot.setAttribute("aria-current", isActive ? "true" : "false");
      });
    }

    previousButton.addEventListener("click", () => updateCarousel(activeIndex - 1));
    nextButton.addEventListener("click", () => updateCarousel(activeIndex + 1));

    carousel.addEventListener("keydown", (event) => {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        updateCarousel(activeIndex - 1);
      }

      if (event.key === "ArrowRight") {
        event.preventDefault();
        updateCarousel(activeIndex + 1);
      }
    });

    carousel.addEventListener("touchstart", (event) => {
      touchStartX = event.changedTouches[0]?.clientX || 0;
    }, { passive: true });

    carousel.addEventListener("touchend", (event) => {
      const touchEndX = event.changedTouches[0]?.clientX || 0;
      const swipeDistance = touchEndX - touchStartX;

      if (Math.abs(swipeDistance) > 44) {
        updateCarousel(activeIndex + (swipeDistance < 0 ? 1 : -1));
      }
    }, { passive: true });

    updateCarousel(activeIndex);
  });
}

function initBlogListing() {
  const page = document.querySelector(".blog-page");
  const grid = page?.querySelector(".w-dyn-items");
  const cards = grid ? Array.from(grid.querySelectorAll(":scope > .w-dyn-item")) : [];

  if (!page || !grid || !cards.length || page.dataset.blogEnhanced === "true") {
    return;
  }

  page.dataset.blogEnhanced = "true";

  const categories = [
    { id: "climatisation", label: "Climatisation", terms: ["climatisation", "clim-", "confort-thermique", "ete"] },
    { id: "chauffage", label: "Chauffage", terms: ["chauffage", "pompe-a-chaleur", "pac-"] },
    { id: "electricite", label: "Électricité", terms: ["electric", "tableau", "courant"] },
    { id: "plomberie", label: "Plomberie", terms: ["plomb", "fuite", "eau", "pression"] },
    { id: "renovation", label: "Rénovation", terms: ["renovation", "isolation", "energetique", "aides-"] },
    { id: "depannage", label: "Dépannage", terms: ["depannage", "entretien", "panne", "refroidit-plus", "duree-vie"] },
  ];

  function getCategory(card) {
    const searchable = `${card.querySelector("a")?.getAttribute("href") || ""} ${card.textContent || ""}`.toLocaleLowerCase("fr");
    const priority = ["depannage", "electricite", "plomberie", "renovation", "chauffage", "climatisation"];
    return priority
      .map((id) => categories.find((category) => category.id === id))
      .find((category) => category?.terms.some((term) => searchable.includes(term))) || categories[0];
  }

  cards.forEach((card) => {
    const category = getCategory(card);
    const titleBlock = card.querySelector(".blog-widget-text-block");
    const linkText = card.querySelector(".blog-widget-button .button-text");

    card.dataset.category = category.id;
    if (titleBlock && !titleBlock.querySelector(".cn-blog-category")) {
      const badge = document.createElement("span");
      badge.className = "cn-blog-category";
      badge.textContent = category.label;
      titleBlock.prepend(badge);
    }
    if (linkText) {
      linkText.textContent = "Lire l’article →";
    }
  });

  const featuredSource = cards[0];
  const featuredLink = featuredSource.querySelector("a");
  if (featuredLink) {
    const featured = document.createElement("article");
    featured.className = "cn-blog-featured";
    featured.setAttribute("aria-label", "Article à la une");
    featured.innerHTML = `<a class="cn-blog-featured-link" href="${featuredLink.getAttribute("href")}">
      <div class="cn-blog-featured-media">${featuredLink.querySelector(".blog-widget-image-block")?.innerHTML || ""}</div>
      <div class="cn-blog-featured-content">
        <span class="cn-blog-featured-label">À la une</span>
        ${featuredLink.querySelector(".blog-slide-meta-block")?.outerHTML || ""}
        ${featuredLink.querySelector(".blog-widget-text-block")?.innerHTML || ""}
        <span class="cn-blog-featured-cta">Lire l’article →</span>
      </div>
    </a>`;
    page.querySelector(".blog-content-wrapper")?.before(featured);
    featuredSource.dataset.featuredSource = "true";
  }

  const toolbar = document.createElement("div");
  toolbar.className = "cn-blog-toolbar";
  toolbar.innerHTML = `<div class="cn-blog-toolbar-heading">
    <h2>Explorez nos conseils</h2>
    <p>Trouvez rapidement les réponses adaptées à votre projet.</p>
  </div><div class="cn-blog-filters" role="group" aria-label="Filtrer les articles"></div>`;
  page.querySelector(".blog-content-wrapper")?.before(toolbar);

  const filters = toolbar.querySelector(".cn-blog-filters");
  const filterOptions = [{ id: "all", label: "Tous" }, ...categories];
  let activeCategory = "all";
  let visibleLimit = 12;

  filterOptions.forEach((option) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "cn-blog-filter";
    button.dataset.filter = option.id;
    button.textContent = option.label;
    button.setAttribute("aria-pressed", option.id === "all" ? "true" : "false");
    filters.append(button);
  });

  const resultsStatus = document.createElement("p");
  resultsStatus.className = "cn-blog-results-status sr-only";
  resultsStatus.setAttribute("aria-live", "polite");
  page.querySelector(".blog-content-wrapper")?.append(resultsStatus);

  const loadMore = document.createElement("button");
  loadMore.type = "button";
  loadMore.className = "cn-blog-load-more";
  loadMore.textContent = "Voir plus d’articles";
  page.querySelector(".blog-content-wrapper")?.append(loadMore);

  function createInlineCta(index) {
    const cta = document.createElement("aside");
    cta.className = "cn-blog-grid-cta";
    cta.dataset.dynamicCta = "true";
    cta.setAttribute("role", "listitem");
    cta.innerHTML = `<div><span>Votre projet à Nice</span><strong>Besoin d’un conseil ou d’un devis personnalisé ?</strong></div>
      <a href="/demande-devis/">Demander un devis gratuit →</a>`;
    cta.dataset.ctaIndex = String(index);
    return cta;
  }

  function refreshCards() {
    grid.querySelectorAll("[data-dynamic-cta]").forEach((cta) => cta.remove());
    const matchingCards = cards.filter((card) => card.dataset.featuredSource !== "true" && (activeCategory === "all" || card.dataset.category === activeCategory));

    cards.forEach((card) => {
      const matches = matchingCards.includes(card);
      const position = matchingCards.indexOf(card);
      card.hidden = !matches || position >= visibleLimit;
    });

    const shownCards = matchingCards.slice(0, visibleLimit);
    shownCards.forEach((card, index) => {
      if ((index + 1) % 6 === 0) {
        card.insertAdjacentElement("afterend", createInlineCta(index + 1));
      }
    });

    loadMore.hidden = matchingCards.length <= visibleLimit;
    resultsStatus.textContent = `${Math.min(matchingCards.length, visibleLimit)} article${matchingCards.length > 1 ? "s" : ""} affiché${matchingCards.length > 1 ? "s" : ""}.`;
  }

  filters.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-filter]");
    if (!button) return;

    activeCategory = button.dataset.filter;
    visibleLimit = 12;
    filters.querySelectorAll("button").forEach((filter) => {
      filter.setAttribute("aria-pressed", filter === button ? "true" : "false");
    });
    refreshCards();
  });

  loadMore.addEventListener("click", () => {
    visibleLimit += 12;
    refreshCards();
  });

  refreshCards();
}

mountComponents();
initBlogShareLinks();
initProjectCarousels();
initBlogListing();
