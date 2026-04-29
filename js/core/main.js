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

mountComponents();
