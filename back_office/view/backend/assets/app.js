const themeStorageKey = "intellectai-theme";
const legacyThemeStorageKey = "theme";
const integrationKeys = {
  session: "intellectai-session",
  profile: "intellectai-profile",
  apiBaseUrl: "intellectai-api-base-url"
};
const integrationRoutes = {
  frontOfficeLogin: "../backend/index.html"
};
const root = document.documentElement;

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

function removeStorageItem(key) {
  try {
    localStorage.removeItem(key);
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

function normalizeProfile(rawProfile) {
  if (!rawProfile || typeof rawProfile !== "object") {
    return null;
  }

  const email = typeof rawProfile.email === "string" ? rawProfile.email.trim() : "";
  const fullName = typeof rawProfile.fullName === "string" ? rawProfile.fullName.trim() : "";
  const firstName = typeof rawProfile.firstName === "string" ? rawProfile.firstName.trim() : "";
  const lastName = typeof rawProfile.lastName === "string" ? rawProfile.lastName.trim() : "";
  const resolvedFullName = fullName || `${firstName} ${lastName}`.trim() || "Client";
  const resolvedFirstName = firstName || resolvedFullName.split(" ")[0] || "Client";
  const resolvedLastName = lastName || resolvedFullName.split(" ").slice(1).join(" ");
  const role = typeof rawProfile.role === "string" ? rawProfile.role : "client";

  return {
    fullName: resolvedFullName,
    firstName: resolvedFirstName,
    lastName: resolvedLastName,
    email: email || "client@example.com",
    phone: typeof rawProfile.phone === "string" ? rawProfile.phone : "",
    bio: typeof rawProfile.bio === "string" ? rawProfile.bio : "",
    role
  };
}

function createInitials(profile) {
  if (!profile) {
    return "CL";
  }

  return `${(profile.firstName || "").charAt(0)}${(profile.lastName || "").charAt(0)}`
    .toUpperCase()
    .trim() || "CL";
}

function redirectToFrontOffice() {
  window.location.href = integrationRoutes.frontOfficeLogin;
}

function getSession() {
  return readJsonFromStorage(integrationKeys.session);
}

function getPersistedProfile() {
  return normalizeProfile(readJsonFromStorage(integrationKeys.profile));
}

function getThemePreference() {
  const savedTheme = getStorageItem(themeStorageKey) || getStorageItem(legacyThemeStorageKey);
  if (savedTheme === "light" || savedTheme === "dark") {
    return savedTheme;
  }

  return window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
}

function persistThemePreference(theme) {
  setStorageItem(themeStorageKey, theme);
  setStorageItem(legacyThemeStorageKey, theme);
}

function syncThemeToggle() {
  const themeToggle = document.querySelector("[data-theme-toggle]");
  if (!themeToggle) {
    return;
  }

  const nextTheme = root.dataset.theme === "light" ? "dark" : "light";
  themeToggle.setAttribute("aria-label", `Switch to ${nextTheme} mode`);
  themeToggle.setAttribute("title", `Switch to ${nextTheme} mode`);
}

function updateIdentityUI(profile) {
  const displayName = profile.fullName;
  const displayEmail = profile.email;
  const displayRole = profile.role === "admin" ? "Admin" : "Client";
  const initials = createInitials(profile);

  document.querySelectorAll(".avatar").forEach((node) => {
    node.textContent = initials;
  });

  document.querySelectorAll(".profile-copy strong").forEach((node) => {
    node.textContent = displayName;
  });

  document.querySelectorAll(".profile-copy span").forEach((node) => {
    node.textContent = displayEmail;
  });

  document.querySelectorAll(".profile-dropdown-card strong").forEach((node) => {
    node.textContent = displayName;
  });

  document.querySelectorAll(".profile-dropdown-card span").forEach((node) => {
    node.textContent = `${displayRole} account`;
  });

  document.querySelectorAll(".topbar-avatar").forEach((node) => {
    node.setAttribute("alt", `${displayName} profile`);
  });
}

function findProfileField(labelText) {
  const boxes = document.querySelectorAll(".profile-form-grid .control-box");
  for (const box of boxes) {
    const label = box.querySelector(".control-label");
    const field = box.querySelector("input, textarea, select");
    if (!label || !field) {
      continue;
    }

    if (label.textContent.trim().toLowerCase().includes(labelText.toLowerCase())) {
      return field;
    }
  }

  return null;
}

function hydrateProfileForm(profile) {
  const firstNameField = findProfileField("First Name");
  const lastNameField = findProfileField("Last Name");
  const emailField = findProfileField("Email/Number");
  const phoneField = findProfileField("Phone Number");
  const bioField = findProfileField("Bio");

  if (firstNameField) {
    firstNameField.value = profile.firstName || "";
  }

  if (lastNameField) {
    lastNameField.value = profile.lastName || "";
  }

  if (emailField) {
    emailField.value = profile.email || "";
  }

  if (phoneField) {
    phoneField.value = profile.phone || "";
  }

  if (bioField) {
    bioField.value = profile.bio || "";
  }
}

function collectProfileFromForm(existingProfile) {
  const firstNameField = findProfileField("First Name");
  const lastNameField = findProfileField("Last Name");
  const emailField = findProfileField("Email/Number");
  const phoneField = findProfileField("Phone Number");
  const bioField = findProfileField("Bio");

  const firstName = firstNameField?.value.trim() || existingProfile.firstName;
  const lastName = lastNameField?.value.trim() || existingProfile.lastName;
  const email = emailField?.value.trim() || existingProfile.email;
  const fullName = `${firstName} ${lastName}`.trim() || existingProfile.fullName;

  return normalizeProfile({
    ...existingProfile,
    fullName,
    firstName,
    lastName,
    email,
    phone: phoneField?.value.trim() || "",
    bio: bioField?.value.trim() || ""
  });
}

async function persistProfileWithApi(profile) {
  const apiBase = (getStorageItem(integrationKeys.apiBaseUrl) || "").trim();
  if (!apiBase) {
    return;
  }

  const url = `${apiBase.replace(/\/+$/, "")}/profile`;
  const session = getSession();

  try {
    await fetch(url, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: session?.token ? `Bearer ${session.token}` : ""
      },
      body: JSON.stringify(profile)
    });
  } catch (error) {
    // Use local profile persistence when API is not available.
  }
}

function bindLogout() {
  document.querySelectorAll(".logout-button").forEach((button) => {
    button.addEventListener("click", () => {
      removeStorageItem(integrationKeys.session);
      removeStorageItem(integrationKeys.profile);
      redirectToFrontOffice();
    });
  });
}

function bindProfileSave(activeProfile) {
  const saveButton = Array.from(document.querySelectorAll("button")).find((button) =>
    button.textContent.trim().toLowerCase().startsWith("save profile")
  );

  if (!saveButton || !document.querySelector(".profile-form-grid")) {
    return;
  }

  saveButton.addEventListener("click", async () => {
    const nextProfile = collectProfileFromForm(activeProfile);
    if (!nextProfile) {
      return;
    }

    writeJsonToStorage(integrationKeys.profile, nextProfile);
    const currentSession = getSession();
    if (currentSession && typeof currentSession === "object") {
      writeJsonToStorage(integrationKeys.session, {
        ...currentSession,
        user: {
          ...(currentSession.user || {}),
          ...nextProfile
        }
      });
    }

    updateIdentityUI(nextProfile);
    await persistProfileWithApi(nextProfile);
  });
}

const session = getSession();
let activeSession = session;

if (!activeSession?.token) {
  const guestProfile = normalizeProfile({
    fullName: "Guest User",
    firstName: "Guest",
    lastName: "User",
    email: "guest@intellectai.local",
    role: "client"
  });

  activeSession = {
    token: `guest-token-${Date.now()}`,
    user: guestProfile,
    issuedAt: new Date().toISOString()
  };

  writeJsonToStorage(integrationKeys.session, activeSession);
  writeJsonToStorage(integrationKeys.profile, guestProfile);
}

const sessionProfile = normalizeProfile(activeSession.user);
const profile = getPersistedProfile() || sessionProfile || normalizeProfile({ role: "client" });

root.dataset.theme = getThemePreference();
syncThemeToggle();
updateIdentityUI(profile);
hydrateProfileForm(profile);
bindProfileSave(profile);
bindLogout();

const currentPage = document.body.dataset.page;
for (const link of document.querySelectorAll("[data-nav]")) {
  if (link.dataset.nav === currentPage) {
    link.classList.add("active");
  }

  link.addEventListener("click", () => {
    document.body.classList.remove("nav-open");
  });
}

const navToggle = document.querySelector("[data-nav-toggle]");
const overlay = document.querySelector("[data-overlay]");
const themeToggle = document.querySelector("[data-theme-toggle]");
const profileToggle = document.querySelector("[data-profile-toggle]");
const profileMenuWrap = document.querySelector("[data-profile-wrap]");
const profileMenu = document.querySelector("[data-profile-menu]");

if (navToggle) {
  navToggle.addEventListener("click", () => {
    document.body.classList.toggle("nav-open");
  });
}

if (overlay) {
  overlay.addEventListener("click", () => {
    document.body.classList.remove("nav-open");
  });
}

if (themeToggle) {
  themeToggle.addEventListener("click", () => {
    const nextTheme = root.dataset.theme === "light" ? "dark" : "light";
    root.dataset.theme = nextTheme;
    persistThemePreference(nextTheme);
    syncThemeToggle();
  });
}

if (profileToggle && profileMenuWrap && profileMenu) {
  profileToggle.addEventListener("click", (event) => {
    event.stopPropagation();
    profileMenuWrap.classList.toggle("open");
  });

  profileMenu.addEventListener("click", (event) => {
    event.stopPropagation();
  });

  document.addEventListener("click", () => {
    profileMenuWrap.classList.remove("open");
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      profileMenuWrap.classList.remove("open");
    }
  });
}
