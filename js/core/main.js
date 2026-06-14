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

function initMetaPixel() {
  !function(f,b,e,v,n,t,s){
    if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)
  }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
  fbq('init','2232429740905540');
  fbq('track','PageView');
}

mountComponents();
initBlogShareLinks();
initProjectCarousels();
initMetaPixel();
