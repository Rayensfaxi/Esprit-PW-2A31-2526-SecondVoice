(function () {
  const PROFILE_KEY = "intellectai-profile";
  const SESSION_KEY = "intellectai-session";

  const form = document.getElementById("profile-form");
  if (!form) {
    return;
  }

  const fullNameInput = form.querySelector('input[name="fullName"]');
  const emailInput = form.querySelector('input[name="email"]');
  const phoneInput = form.querySelector('input[name="phone"]');
  const cityInput = form.querySelector('input[name="city"]');
  const bioInput = form.querySelector('textarea[name="bio"]');
  const photoInput = document.getElementById("profile-photo-input");
  const photoClearButton = document.getElementById("profile-photo-clear");
  const photoPreview = document.getElementById("profile-photo-preview");
  const photoInitials = document.getElementById("profile-photo-initials");
  const feedback = document.getElementById("profile-feedback");
  const summaryName = document.getElementById("profile-summary-name");
  const summaryEmail = document.getElementById("profile-summary-email");

  let currentPhotoDataUrl = "";

  function readJson(key) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      return null;
    }
  }

  function writeJson(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (error) {
      return false;
    }
  }

  function getInitials(fullName, email) {
    const base = (fullName || "").trim();
    if (base) {
      const parts = base.split(" ").filter(Boolean);
      return parts.slice(0, 2).map((part) => part[0].toUpperCase()).join("");
    }

    const emailPrefix = (email || "").split("@")[0] || "GU";
    return emailPrefix.slice(0, 2).toUpperCase();
  }

  function setPreview(photoDataUrl, initials) {
    if (photoDataUrl) {
      photoPreview.style.backgroundImage = `url("${photoDataUrl}")`;
      photoPreview.classList.add("has-image");
    } else {
      photoPreview.style.backgroundImage = "";
      photoPreview.classList.remove("has-image");
    }

    photoInitials.textContent = initials || "GU";
  }

  function setSummary(profile) {
    summaryName.textContent = profile.fullName || "Utilisateur invite";
    summaryEmail.textContent = profile.email || "utilisateur@exemple.com";
  }

  function normalizeProfile(profile) {
    const fullName = (profile?.fullName || "").trim();
    const parts = fullName.split(" ").filter(Boolean);
    const firstName = (profile?.firstName || parts[0] || "").trim();
    const lastName = (profile?.lastName || parts.slice(1).join(" ") || "").trim();

    return {
      fullName: fullName || `${firstName} ${lastName}`.trim() || "Utilisateur invite",
      firstName: firstName || "Invite",
      lastName: lastName,
      email: (profile?.email || "").trim(),
      phone: (profile?.phone || "").trim(),
      city: (profile?.city || "").trim(),
      bio: (profile?.bio || "").trim(),
      photoDataUrl: profile?.photoDataUrl || "",
      role: profile?.role || "client"
    };
  }

  const session = readJson(SESSION_KEY);
  const storedProfile = normalizeProfile(readJson(PROFILE_KEY) || session?.user || {});

  fullNameInput.value = storedProfile.fullName || "";
  emailInput.value = storedProfile.email || "";
  phoneInput.value = storedProfile.phone || "";
  cityInput.value = storedProfile.city || "";
  bioInput.value = storedProfile.bio || "";

  currentPhotoDataUrl = storedProfile.photoDataUrl || "";
  setPreview(currentPhotoDataUrl, getInitials(storedProfile.fullName, storedProfile.email));
  setSummary(storedProfile);

  photoInput.addEventListener("change", () => {
    const file = photoInput.files && photoInput.files[0];
    if (!file) {
      return;
    }

    if (!file.type.startsWith("image/")) {
      feedback.textContent = "Veuillez choisir un fichier image.";
      feedback.classList.add("error");
      photoInput.value = "";
      return;
    }

    if (file.size > 4 * 1024 * 1024) {
      feedback.textContent = "Image trop volumineuse (max 4 Mo).";
      feedback.classList.add("error");
      photoInput.value = "";
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      currentPhotoDataUrl = String(reader.result || "");
      setPreview(currentPhotoDataUrl, getInitials(fullNameInput.value, emailInput.value));
      feedback.textContent = "Photo chargee. Cliquez sur Enregistrer le profil pour la conserver.";
      feedback.classList.remove("error");
    };
    reader.readAsDataURL(file);
  });

  photoClearButton.addEventListener("click", () => {
    currentPhotoDataUrl = "";
    photoInput.value = "";
    setPreview("", getInitials(fullNameInput.value, emailInput.value));
    feedback.textContent = "Photo supprimee.";
    feedback.classList.remove("error");
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const normalized = normalizeProfile({
      fullName: fullNameInput.value,
      email: emailInput.value,
      phone: phoneInput.value,
      city: cityInput.value,
      bio: bioInput.value,
      photoDataUrl: currentPhotoDataUrl,
      role: storedProfile.role
    });

    if (!normalized.fullName || normalized.fullName.length < 6) {
      feedback.textContent = "Veuillez saisir un nom complet valide (6 caracteres minimum).";
      feedback.classList.add("error");
      return;
    }

    if (!normalized.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized.email)) {
      feedback.textContent = "Veuillez saisir une adresse e-mail valide.";
      feedback.classList.add("error");
      return;
    }

    if (normalized.phone && !/^\+?[0-9]{8,15}$/.test(normalized.phone.replace(/\s+/g, ""))) {
      feedback.textContent = "Le numero de telephone doit contenir entre 8 et 15 chiffres.";
      feedback.classList.add("error");
      return;
    }

    const profileSaved = writeJson(PROFILE_KEY, normalized);

    if (session && session.token) {
      writeJson(SESSION_KEY, {
        ...session,
        user: normalized
      });
    } else {
      writeJson(SESSION_KEY, {
        token: `demo-token-${Date.now()}`,
        user: normalized,
        issuedAt: new Date().toISOString()
      });
    }

    setPreview(normalized.photoDataUrl, getInitials(normalized.fullName, normalized.email));
    setSummary(normalized);

    if (profileSaved) {
      feedback.textContent = "Profil enregistre avec succes.";
      feedback.classList.remove("error");
    } else {
      feedback.textContent = "Impossible d'enregistrer le profil dans ce navigateur.";
      feedback.classList.add("error");
    }
  });
})();
