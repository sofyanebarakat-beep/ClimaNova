const SITE_SEGMENT = "ClimaNova";

function pathSegments() {
  return window.location.pathname.split("/").filter(Boolean);
}

export function isDeployedUnderSiteSegment() {
  return pathSegments()[0] === SITE_SEGMENT;
}

export function assetUrl(path = "") {
  const cleanPath = path.replace(/^\/+/, "");

  if (isDeployedUnderSiteSegment()) {
    return `/${SITE_SEGMENT}/${cleanPath}`;
  }

  const directoryDepth = pathSegments().slice(0, -1).length;
  return `${"../".repeat(directoryDepth)}${cleanPath}`;
}

export function currentPath() {
  return window.location.pathname.replace(/\.html$/, "").replace(/\/$/, "") || "/";
}
