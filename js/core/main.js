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

mountComponents();
initBlogShareLinks();
