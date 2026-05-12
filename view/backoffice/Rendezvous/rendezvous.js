console.log("rendezvous.js charge avec succes");

window.filterAppointments = function () {
  const searchInput = document.getElementById("appointmentSearch");
  const statusSelect = document.getElementById("statusFilter");

  const filterSearch = (searchInput?.value || "").toLowerCase();
  const filterStatus = (statusSelect?.value || "").toLowerCase();

  const table = document.querySelector(".users-table");
  if (!table) return;

  const rows = table.getElementsByTagName("tr");

  for (let i = 1; i < rows.length; i++) {
    const citizenCell = rows[i].getElementsByTagName("td")[0];
    const serviceCell = rows[i].getElementsByTagName("td")[1];
    const statusCell = rows[i].getElementsByTagName("td")[4];

    const citizenText = (citizenCell?.textContent || "").toLowerCase();
    const serviceText = (serviceCell?.textContent || "").toLowerCase();
    const statusText = (statusCell?.textContent || "").trim().toLowerCase();

    const matchSearch = citizenText.includes(filterSearch) || serviceText.includes(filterSearch);
    const matchStatus = filterStatus === "" || statusText === filterStatus;

    rows[i].style.display = matchSearch && matchStatus ? "" : "none";
  }
};

window.toggleStatus = function (id, currentStatus) {
  const rawStatus = String(currentStatus || "");
  const normalizedStatus = rawStatus.toLowerCase();
  const isCancelled = normalizedStatus.includes("annul");
  const isConfirmed = normalizedStatus.includes("confirm");

  if (isCancelled) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Action impossible",
        text: "Impossible de modifier le statut d'un rendez-vous annule.",
        confirmButtonColor: "#1fb47a",
      });
    } else {
      alert("Impossible de modifier le statut d'un rendez-vous annule.");
    }
    return;
  }

  const nextStatus = isConfirmed ? "wait" : "confirm";
  const statusLabel = isConfirmed ? "mettre en attente" : "confirmer";

  if (typeof Swal === "undefined") {
    if (confirm("Voulez-vous " + statusLabel + " ce rendez-vous ?")) {
      window.location.href = "toggleStatutRendezvous.php?action=" + nextStatus + "&id=" + id;
    }
    return;
  }

  Swal.fire({
    title: "Changer le statut ?",
    text: "Voulez-vous " + statusLabel + " ce rendez-vous ?",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#1fb47a",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Oui, changer",
    cancelButtonText: "Annuler",
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "toggleStatutRendezvous.php?action=" + nextStatus + "&id=" + id;
    }
  });
};

let currentRdv = null;

function setText(id, value) {
  const element = document.getElementById(id);
  if (element) {
    element.textContent = value || "-";
  }
}

function setValue(id, value) {
  const element = document.getElementById(id);
  if (element) {
    element.value = value || "";
  }
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove("active");
  }
  if (!document.querySelector(".modal-overlay.active")) {
    document.body.style.overflow = "";
  }
}

window.openDetails = function (rdv) {
  currentRdv = rdv;
  setText("det-citizen", "Citoyen #" + (rdv.id_citoyen || "-"));
  setText("det-service", rdv.service);
  setText("det-assistant", rdv.assistant);
  setText("det-datetime", (rdv.date_rdv || "-") + " a " + (rdv.heure_rdv || "-"));
  setText("det-mode", rdv.mode);
  setText("det-status", rdv.statut);
  setText("det-notes", rdv.remarques);

  const qrSection = document.getElementById("qr-section");
  const qrImg = document.getElementById("det-qrcode");
  const qrDownload = document.getElementById("btn-download-qr");
  const qrOpen = document.getElementById("btn-open-qr");
  const qrError = document.getElementById("qr-error");

  if (qrSection && qrImg && qrDownload) {
    qrSection.style.display = "block";
    qrImg.style.display = "none";
    qrDownload.style.display = "none";
    if (qrOpen) qrOpen.style.display = "none";
    if (qrError) qrError.style.display = "none";

    fetch("generateQR.php?id=" + rdv.id)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          qrImg.src = data.qrPath + "?t=" + new Date().getTime();
          qrImg.style.display = "block";
          qrDownload.href = data.qrPath;
          qrDownload.style.display = "inline-block";
          if (qrOpen) {
            qrOpen.href = data.viewUrl || ("viewQRCode.php?id=" + encodeURIComponent(String(rdv.id)));
            qrOpen.style.display = "inline-block";
          }
        } else {
          qrImg.style.display = "none";
          qrDownload.style.display = "none";
          if (qrOpen) qrOpen.style.display = "none";
          if (qrError) {
            qrError.textContent = data.message;
            qrError.style.display = "block";
          }
        }
      })
      .catch(() => {
        qrImg.style.display = "none";
        qrDownload.style.display = "none";
        if (qrOpen) qrOpen.style.display = "none";
        if (qrError) {
          qrError.textContent = "Erreur de connexion au serveur.";
          qrError.style.display = "block";
        }
      });
  }

  openModal("detailsModal");
};

window.closeDetails = function () {
  closeModal("detailsModal");
};

window.editRdv = function (rdv) {
  setValue("edit-id", rdv.id);
  setValue("edit-id-citoyen", rdv.id_citoyen);
  setValue("edit-service", rdv.service_id);
  setValue("edit-assistant", rdv.assistant);
  setValue("edit-date", rdv.date_rdv);
  setValue("edit-heure", rdv.heure_rdv);
  setValue("edit-mode", rdv.mode);
  setValue("edit-statut", rdv.statut);
  setValue("edit-remarques", rdv.remarques);

  document.querySelectorAll(".js-error").forEach((el) => (el.style.display = "none"));
  openModal("editModal");
};

window.closeEditModal = function () {
  closeModal("editModal");
};

function validateEditField(fieldId) {
  const field = document.getElementById(fieldId);
  const errorElement = document.getElementById(fieldId + "-error");
  if (!field || !errorElement) return true;

  let isValid = true;
  let errorMsg = "";

  if (fieldId === "edit-service" || fieldId === "edit-assistant") {
    if (!field.value) {
      isValid = false;
      errorMsg = "Veuillez choisir un choix.";
    }
  } else if (fieldId === "edit-date") {
    if (!field.value) {
      isValid = false;
      errorMsg = "Date invalide.";
    }
  } else if (fieldId === "edit-heure") {
    if (!field.value) {
      isValid = false;
      errorMsg = "Veuillez choisir une heure.";
    } else {
      const hParts = field.value.split(":");
      const timeInMinutes = parseInt(hParts[0], 10) * 60 + parseInt(hParts[1], 10);
      if (timeInMinutes < 8 * 60 + 10 || timeInMinutes > 17 * 60 + 30) {
        isValid = false;
        errorMsg = "L'heure doit etre entre 08:10 et 17:30.";
      }
    }
  } else if (fieldId === "edit-remarques") {
    const words = field.value.trim().match(/\S+/g) || [];
    if (field.value.trim() === "") {
      isValid = false;
      errorMsg = "Ce champ est obligatoire.";
    } else if (words.length > 50) {
      isValid = false;
      errorMsg = "Max 50 mots (actuellement : " + words.length + " mots).";
    }
  }

  if (isValid) {
    errorElement.style.display = "none";
  } else {
    errorElement.textContent = errorMsg;
    errorElement.style.display = "block";
  }
  return isValid;
}

["edit-service", "edit-assistant", "edit-date", "edit-heure", "edit-remarques"].forEach((id) => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener("input", () => validateEditField(id));
    el.addEventListener("change", () => validateEditField(id));
  }
});

if (document.getElementById("editForm")) {
  document.getElementById("editForm").addEventListener("submit", function (e) {
    let isValidForm = true;
    ["edit-service", "edit-assistant", "edit-date", "edit-heure", "edit-remarques"].forEach((id) => {
      if (!validateEditField(id)) isValidForm = false;
    });
    if (!isValidForm) e.preventDefault();
  });
}

window.confirmDelete = function (id) {
  if (typeof Swal === "undefined") {
    if (confirm("Etes-vous sur de vouloir supprimer ce rendez-vous ?")) {
      window.location.href = "deleteRendezvous.php?id=" + id;
    }
    return;
  }

  Swal.fire({
    title: "Etes-vous sur ?",
    text: "Cette action est irreversible.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Oui, supprimer",
    cancelButtonText: "Annuler",
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "deleteRendezvous.php?id=" + id;
    }
  });
};

window.onclick = function (e) {
  if (e.target.classList.contains("modal-overlay")) {
    if (typeof window.closeDetails === "function") window.closeDetails();
    if (typeof window.closeEditModal === "function") window.closeEditModal();
  }
};
