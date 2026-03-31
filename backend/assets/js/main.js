const root = document.documentElement;
const themeToggle = document.querySelector("[data-theme-toggle]");
const themeLabel = document.querySelector("[data-theme-label]");
const userShell = document.querySelector("[data-user-shell]");
const userToggle = document.querySelector("[data-user-toggle]");
const userClose = document.querySelector("[data-user-close]");
const userPanel = document.querySelector(".user-panel");
const userBackdrop = document.querySelector(".user-backdrop");
const authTabs = document.querySelectorAll("[data-auth-tab]");
const authPanels = document.querySelectorAll("[data-auth-panel]");
const menuToggle = document.querySelector("[data-menu-toggle]");
const nav = document.querySelector("[data-nav]");

const INTEGRATION_KEYS = {
  session: "intellectai-session",
  profile: "intellectai-profile",
  apiBaseUrl: "intellectai-api-base-url"
};

const INTEGRATION_ROUTES = {
  backOfficeHome: "../template back office/index.html"
};

function getStorageItem(key) {
  try {
    return localStorage.getItem(key);
  } catch (error) {
    return null;
  }
}

function setStorageItem(key, value) {
  try {
    localStorage.setItem(key, value);
    return true;
  } catch (error) {
    return false;
  }
}

function readJsonFromStorage(key) {
  const rawValue = getStorageItem(key);
  if (!rawValue) {
    return null;
  }

  try {
    return JSON.parse(rawValue);
  } catch (error) {
    return null;
  }
}

function writeJsonToStorage(key, value) {
  return setStorageItem(key, JSON.stringify(value));
}

function getSession() {
  return readJsonFromStorage(INTEGRATION_KEYS.session);
}

function getStoredProfile() {
  return readJsonFromStorage(INTEGRATION_KEYS.profile);
}

function getPreferredTheme() {
  const storedTheme = getStorageItem("theme");
  if (storedTheme) {
    return storedTheme;
  }

  return window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
}

function applyTheme(theme) {
  root.dataset.theme = theme;
  if (themeLabel) {
    themeLabel.textContent = theme === "light" ? "Dark" : "Light";
  }
}

function normalizeProfile(rawProfile) {
  if (!rawProfile || typeof rawProfile !== "object") {
    return null;
  }

  const email = typeof rawProfile.email === "string" ? rawProfile.email.trim() : "";
  const fullName = typeof rawProfile.fullName === "string" ? rawProfile.fullName.trim() : "";
  const firstName = typeof rawProfile.firstName === "string" ? rawProfile.firstName.trim() : "";
  const lastName = typeof rawProfile.lastName === "string" ? rawProfile.lastName.trim() : "";
  const resolvedFullName = fullName || `${firstName} ${lastName}`.trim() || "Client";

  return {
    fullName: resolvedFullName,
    firstName: firstName || resolvedFullName.split(" ")[0] || "Client",
    lastName: lastName || resolvedFullName.split(" ").slice(1).join(" "),
    email: email || "client@example.com",
    role: typeof rawProfile.role === "string" ? rawProfile.role : "client"
  };
}

function createInitials(profile) {
  if (!profile) {
    return "CL";
  }

  if (profile.firstName || profile.lastName) {
    return `${(profile.firstName || "").charAt(0)}${(profile.lastName || "").charAt(0)}`
      .toUpperCase()
      .trim() || "CL";
  }

  return profile.fullName
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("") || "CL";
}

function updateUserModalCopy(tabName, message) {
  const panel = document.querySelector(`[data-auth-panel="${tabName}"]`);
  const helper = panel?.querySelector(".auth-helper");
  if (helper) {
    helper.textContent = message;
  }
}

function updateFrontOfficeIdentity(profile, hasSession) {
  const initials = createInitials(profile);
  const displayName = profile?.fullName || "Client";
  const displayEmail = profile?.email || "client@example.com";
  const title = document.querySelector(".user-panel-title");
  const modalCopy = document.querySelector(".user-modal-copy");
  const footerLink = document.querySelector(".user-panel-footer a");

  document.querySelectorAll(".user-avatar").forEach((avatar) => {
    avatar.textContent = initials;
  });

  const toggleLabel = userToggle?.querySelector("span");
  if (toggleLabel) {
    toggleLabel.textContent = initials;
  }

  if (title) {
    title.textContent = hasSession ? `Welcome, ${displayName}` : "Welcome back";
  }

  if (modalCopy) {
    modalCopy.textContent = hasSession
      ? `Connected as ${displayEmail}. Open the back office dashboard to continue.`
      : "Sign in to access your projects, invoices, and support.";
  }

  if (footerLink) {
    if (hasSession) {
      footerLink.textContent = "Open Back Office";
      footerLink.href = INTEGRATION_ROUTES.backOfficeHome;
    } else {
      footerLink.textContent = "Client support";
      footerLink.href = "contact.html";
    }
  }
}

function saveAuthState(profile, token) {
  const normalized = normalizeProfile(profile);
  if (!normalized) {
    return;
  }

  writeJsonToStorage(INTEGRATION_KEYS.profile, normalized);
  writeJsonToStorage(INTEGRATION_KEYS.session, {
    token: token || `demo-token-${Date.now()}`,
    user: normalized,
    issuedAt: new Date().toISOString()
  });
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

async function authenticateWithApi(mode, payload) {
  const apiBase = (getStorageItem(INTEGRATION_KEYS.apiBaseUrl) || "").trim();
  if (!apiBase) {
    return null;
  }

  const endpoint = mode === "register" ? "/auth/register" : "/auth/login";
  const url = `${apiBase.replace(/\/+$/, "")}${endpoint}`;

  try {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      return null;
    }

    return await response.json();
  } catch (error) {
    return null;
  }
}

function redirectToBackOffice() {
  window.location.href = INTEGRATION_ROUTES.backOfficeHome;
}

function ensureDashboardButton() {
  const headerActions = document.querySelector(".header-actions");
  if (!headerActions) {
    return;
  }

  let dashboardButton = headerActions.querySelector("[data-dashboard-link]");
  if (!dashboardButton) {
    dashboardButton = document.createElement("a");
    dashboardButton.className = "btn btn-secondary";
    dashboardButton.setAttribute("data-dashboard-link", "true");
    dashboardButton.textContent = "DASHBOARD";

    const userShellNode = headerActions.querySelector(".user-shell");
    if (userShellNode && userShellNode.nextSibling) {
      headerActions.insertBefore(dashboardButton, userShellNode.nextSibling);
    } else {
      headerActions.appendChild(dashboardButton);
    }
  }

  dashboardButton.setAttribute("href", INTEGRATION_ROUTES.backOfficeHome);
}

function setAuthTab(tabName) {
  authTabs.forEach((tab) => {
    tab.classList.toggle("is-active", tab.dataset.authTab === tabName);
  });

  authPanels.forEach((panel) => {
    panel.classList.toggle("is-active", panel.dataset.authPanel === tabName);
  });
}

function setUserModal(open) {
  if (!userPanel || !userBackdrop) {
    return;
  }

  userPanel.classList.toggle("is-open", open);
  userBackdrop.classList.toggle("is-open", open);
}

function getLoginPayload(form) {
  const emailField = form.querySelector('input[type="email"]');
  const passwordField = form.querySelector('input[type="password"]');

  return {
    email: emailField?.value.trim() || "",
    password: passwordField?.value || ""
  };
}

function getRegisterPayload(form) {
  const inputs = form.querySelectorAll("input");
  const fullName = inputs[0]?.value.trim() || "";
  const email = inputs[1]?.value.trim() || "";
  const password = inputs[2]?.value || "";
  const [firstName = "", ...lastNameParts] = fullName.split(" ").filter(Boolean);

  return {
    fullName,
    firstName,
    lastName: lastNameParts.join(" "),
    email,
    password
  };
}

function wireAuthPanel(panelName, payloadBuilder) {
  const panel = document.querySelector(`[data-auth-panel="${panelName}"]`);
  const form = panel?.querySelector("form.auth-form");
  const actionButton = form?.querySelector("button.btn");

  if (!panel || !form || !actionButton) {
    return;
  }

  const submit = async () => {
    const payload = payloadBuilder(form);
    if (!isValidEmail(payload.email)) {
      updateUserModalCopy(panelName, "Please enter a valid email address.");
      return;
    }

    if (!payload.password || payload.password.length < 4) {
      updateUserModalCopy(panelName, "Please enter a password (minimum 4 characters).");
      return;
    }

    const initialButtonText = actionButton.textContent;
    actionButton.disabled = true;
    actionButton.textContent = "Please wait...";

    const apiResponse = await authenticateWithApi(panelName, payload);
    const profileFromApi = normalizeProfile(apiResponse?.user || apiResponse?.profile);
    const fallbackProfile = normalizeProfile(payload);
    const finalProfile = profileFromApi || fallbackProfile;
    const finalToken = apiResponse?.token;

    if (!finalProfile) {
      updateUserModalCopy(panelName, "Unable to create a session. Please try again.");
      actionButton.disabled = false;
      actionButton.textContent = initialButtonText;
      return;
    }

    saveAuthState(finalProfile, finalToken);
    updateFrontOfficeIdentity(finalProfile, true);
    updateUserModalCopy(panelName, "Authenticated. Redirecting to back office...");
    setUserModal(false);
    redirectToBackOffice();

    actionButton.disabled = false;
    actionButton.textContent = initialButtonText;
  };

  actionButton.addEventListener("click", submit);
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    submit();
  });
}

applyTheme(getPreferredTheme());

if (themeToggle) {
  themeToggle.addEventListener("click", () => {
    const nextTheme = root.dataset.theme === "light" ? "dark" : "light";
    setStorageItem("theme", nextTheme);
    applyTheme(nextTheme);
  });
}

if (userPanel && document.body && userPanel.parentElement !== document.body) {
  document.body.appendChild(userPanel);
}

if (userBackdrop && document.body && userBackdrop.parentElement !== document.body) {
  document.body.appendChild(userBackdrop);
}

if (userShell && userToggle) {
  userToggle.addEventListener("click", (event) => {
    event.stopPropagation();
    const shouldOpen = !userPanel?.classList.contains("is-open");
    setUserModal(shouldOpen);
  });

  if (userPanel) {
    userPanel.addEventListener("click", (event) => {
      event.stopPropagation();
    });
  }

  if (userBackdrop) {
    userBackdrop.addEventListener("click", () => {
      setUserModal(false);
    });
  }

  userToggle.addEventListener("click", (event) => {
    event.stopPropagation();
  });

  document.addEventListener("click", () => {
    setUserModal(false);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      setUserModal(false);
    }
  });
}

if (userClose) {
  userClose.addEventListener("click", () => {
    setUserModal(false);
  });
}

authTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    setAuthTab(tab.dataset.authTab);
  });
});

if (authTabs.length && authPanels.length) {
  setAuthTab("login");
}

const storedProfile = normalizeProfile(getStoredProfile()) || normalizeProfile(getSession()?.user);
const hasSession = Boolean(getSession()?.token);
ensureDashboardButton();
updateFrontOfficeIdentity(storedProfile, hasSession);
wireAuthPanel("login", getLoginPayload);
wireAuthPanel("register", getRegisterPayload);

if (menuToggle && nav) {
  menuToggle.addEventListener("click", () => {
    document.body.classList.toggle("menu-open");
  });

  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      document.body.classList.remove("menu-open");
    });
  });
}

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.14 }
);

document.querySelectorAll(".fade-up").forEach((element) => observer.observe(element));
