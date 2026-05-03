// ══════════════════════════════════════════════
// DROPDOWN TRI (inchangé)
// ══════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function () {
    var sortWrapper = document.getElementById('sort-wrapper');
    var sortToggle  = document.getElementById('sort-toggle');

    if (sortToggle && sortWrapper) {
        sortToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            sortWrapper.classList.toggle('open');
        });
        document.addEventListener('click', function () {
            sortWrapper.classList.remove('open');
        });
        sortWrapper.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }
});

// ══════════════════════════════════════════════
// VALIDATION DU FORMULAIRE (avec ajout optionnel pour la vidéo)
// ══════════════════════════════════════════════

function validerCourse() {
    let isvalid = true;

    var Title = document.getElementById("Title");
    var Titlevalue = Title.value;
    removeMsg(Title);
    if (Titlevalue === "") {
        showMsg(Title, "Please enter the title needed", false);
        isvalid = false;
    } else if (Titlevalue.length < 3) {
        showMsg(Title, "Title has to be longer than 3", false);
        isvalid = false;
    } else {
        showMsg(Title, "Valid title", true);
    }

    var Description = document.getElementById("Description");
    var Descriptionvalue = Description.value;
    removeMsg(Description);
    if (Descriptionvalue === "") {
        showMsg(Description, "Please enter the description", false);
        isvalid = false;
    } else if (Descriptionvalue.length < 15) {
        showMsg(Description, "Description has to be longer than 15", false);
        isvalid = false;
    } else {
        showMsg(Description, "Valid Description", true);
    }

    var Duration = document.getElementById("Duration");
    var Durationvalue = Duration.value;
    removeMsg(Duration);
    if (Durationvalue === "") {
        showMsg(Duration, "Please enter the duration", false);
        isvalid = false;
    } else if (Number(Durationvalue) < 1) {
        showMsg(Duration, "Duration has to be longer than 1", false);
        isvalid = false;
    } else if (Number(Durationvalue) > 4) {
        showMsg(Duration, "Duration has to be under 4", false);
        isvalid = false;
    } else {
        showMsg(Duration, "Valid Duration", true);
    }

    const Published = document.querySelector("input[name='Published_AT']");
    const Debut = Published.value;
    removeMsg(Published);
    if (!Debut) {
        showMsg(Published, "Please enter the date.", false);
        isvalid = false;
    } else {
        const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
        if (Debut >= today) {
            showMsg(Published, "Valid date ✓", true);
        } else {
            showMsg(Published, "Date not valid (must be today or later).", false);
            isvalid = false;
        }
    }

    // Optionnel : validation du fichier vidéo (non obligatoire)
    const videoInput = document.querySelector("input[name='upload_video']");
    if (videoInput && videoInput.files.length > 0) {
        const file = videoInput.files[0];
        const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
        const maxSize = 50 * 1024 * 1024; // 50MB
        if (!allowedTypes.includes(file.type)) {
            showMsg(videoInput, "Format non supporté (MP4, WebM, OGG requis).", false);
            isvalid = false;
        } else if (file.size > maxSize) {
            showMsg(videoInput, "Fichier trop volumineux (max 50MB).", false);
            isvalid = false;
        } else {
            // supprimer un éventuel ancien message d'erreur
            removeMsg(videoInput);
        }
    }

    return isvalid;
}

function showMsg(input, message, success) {
    removeMsg(input); // éviter doublons
    const msg = document.createElement("span");
    msg.className = "validation-msg " + (success ? "msg-success" : "msg-error");
    msg.textContent = message;
    input.insertAdjacentElement("afterend", msg);
}

function removeMsg(input) {
    const next = input.nextElementSibling;
    if (next && next.classList.contains("validation-msg")) {
        next.remove();
    }
}