import type { Lang } from "@/lib/api";

export type I18nKey =
  | "nav.home"
  | "nav.scan"
  | "nav.schedule"
  | "nav.profile"
  | "progress.takenLabel"
  | "home.bannerTitle"
  | "home.bannerBody"
  | "home.scheduleToday"
  | "home.noMeds"
  | "home.noMedsToday"
  | "home.dosesLeft"
  | "home.allTaken"
  | "home.viewAll"
  | "schedule.pageTitle"
  | "schedule.pageSubtitle"
  | "schedule.today"
  | "schedule.weekViewTitle"
  | "schedule.viewOnlyOtherDay"
  | "schedule.empty"
  | "schedule.medicineList"
  | "schedule.doseSingular"
  | "schedule.dosePlural"
  | "schedule.medicinesHeadingHint"
  | "scanner.pageTitle"
  | "scanner.pageSubtitle"
  | "profile.pageTitle"
  | "scheduleCard.confirmTitle"
  | "scheduleCard.confirmNo"
  | "scheduleCard.confirmYes"
  | "scheduleCard.takenLine"
  | "medicineList.emptyTitle"
  | "medicineList.emptyHint"
  | "medicineList.remove"
  | "medicineList.info"
  | "medicineDialog.generic"
  | "medicineDialog.dosage"
  | "medicineDialog.purpose"
  | "medicineDialog.precautions"
  | "medicineDialog.schedule"
  | "medicineDialog.notes"
  | "register.pendingNote"
  | "register.activeNote"
  | "profile.language"
  | "profile.aboutTitle"
  | "profile.aboutP1"
  | "profile.aboutP2"
  | "profile.featuresTitle"
  | "profile.featureScan"
  | "profile.featureRemind"
  | "profile.featureConfirm"
  | "profile.featureLang"
  | "profile.featureCommunity"
  | "profile.footer"
  | "profile.editTitle"
  | "profile.username"
  | "profile.fullName"
  | "profile.phone"
  | "profile.age"
  | "profile.save"
  | "profile.passwordTitle"
  | "profile.currentPassword"
  | "profile.newPassword"
  | "profile.changePassword"
  | "scanner.errorNoBarcode"
  | "scanner.errorNotFound"
  | "scanner.titleScan"
  | "scanner.uploadBarcodeImage"
  | "scanner.noCameraHint"
  | "scanner.lookupDisclaimer"
  | "scanner.searchingTitle"
  | "scanner.searchingHint"
  | "scanner.barcodeCodeLabel"
  | "scanner.scanAgain"
  | "scanner.cameraError"
  | "scanner.imageNoBarcode"
  | "scanner.imageNoBarcodeDetail"
  | "scanner.tryNameLookupInstead"
  | "scanner.addFailed"
  | "scanner.openCamera"
  | "scanner.manualEmpty"
  | "scanner.lookupErrorGeneric"
  | "scanner.toastDoctorOnly"
  | "scanner.toastAdded"
  | "scanner.introBody"
  | "admin.title"
  | "admin.verifyAccounts"
  | "admin.tabAccounts"
  | "admin.tabLinks"
  | "admin.tabSchedules"
  | "admin.allAccounts"
  | "admin.loading"
  | "admin.emptyUsers"
  | "admin.approve"
  | "admin.viewVerification"
  | "admin.openVerificationNewTab"
  | "admin.reject"
  | "admin.deactivate"
  | "admin.roleStatus"
  | "admin.hintDefaultLogin"
  | "admin.linkSection"
  | "admin.doctorUsername"
  | "admin.patientUsername"
  | "admin.linkPair"
  | "admin.unlinkPair"
  | "admin.schedulesAll"
  | "admin.edit"
  | "admin.delete"
  | "admin.time"
  | "admin.days"
  | "admin.notes"
  | "admin.patient"
  | "admin.medicine"
  | "admin.doctor"
  | "admin.save"
  | "admin.cancel"
  | "admin.editUserTitle"
  | "admin.editSchedule"
  | "admin.newPasswordOptional"
  | "admin.searchUsersPlaceholder"
  | "admin.noUserMatch"
  | "admin.chooseBothLink"
  | "admin.invalidAge"
  | "admin.deleteAccount"
  | "admin.deleteAccountConfirm"
  | "admin.deleteAccountWarn"
  | "doctor.title"
  | "doctor.linkPatient"
  | "doctor.linkPatientSearch"
  | "doctor.linkPatientPickIdle"
  | "doctor.linkPatientMinChars"
  | "doctor.linkHint"
  | "doctor.prescribeTitle"
  | "doctor.prescribeHint"
  | "doctor.patient"
  | "doctor.medName"
  | "doctor.dosage"
  | "doctor.times"
  | "doctor.howOftenDays"
  | "doctor.howOftenDaysHint"
  | "doctor.saveRx"
  | "doctor.linkAction"
  | "doctor.frequencyTextEn"
  | "doctor.frequencyTextFil"
  | "doctor.purpose"
  | "doctor.warnings"
  | "doctor.scheduleNotesLines"
  | "doctor.scheduleNotesLinesHint"
  | "doctor.medNotesExtra"
  | "doctor.schedulesTitle"
  | "doctor.schedulesTodayHint"
  | "doctor.schedulesSearchPlaceholder"
  | "doctor.schedulesNoMatch"
  | "doctor.scheduleEmpty"
  | "doctor.editSchedule"
  | "doctor.adherenceNotScheduledToday"
  | "doctor.adherencePending"
  | "doctor.adherenceTakenOnTime"
  | "doctor.adherenceTakenLate"
  | "doctor.adherenceTakenUnknown"
  | "doctor.adherenceMarkedNotTaken"
  | "doctor.adherenceConfirmedAt"
  | "doctor.refreshSchedules"
  | "scanner.lookupCodeButton"
  | "scanner.howItWorksTitle"
  | "scanner.frameHint"
  | "scanner.cancel"
  | "scanner.resultTitle"
  | "scanner.addToList"
  | "scanner.listNotePatient"
  | "scanner.dosageHeading"
  | "scanner.frequencyHeading"
  | "scanner.manualBarcodeHelp"
  | "scanner.barcodeNumberLabel"
  | "scanner.barcodePlaceholder"
  | "scanner.nameLookupTitle"
  | "scanner.nameLookupHelp"
  | "scanner.productNameLabel"
  | "scanner.productNamePlaceholder"
  | "scanner.nameLookupButton"
  | "scanner.nameLookupEmpty";

const en: Record<I18nKey, string> = {
  "nav.home": "Home",
  "nav.scan": "Scan",
  "nav.schedule": "Schedule",
  "nav.profile": "Profile",
  "progress.takenLabel": "taken",
  "home.bannerTitle": "Prescription from your doctor",
  "home.bannerBody":
    "Your medicine list and times are set by your doctor in PharSayo after your visit. You can look up details with the barcode scanner, but scans do not add items to your list automatically.",
  "home.scheduleToday": "Today's schedule",
  "home.noMeds": "No medicines scheduled",
  "home.noMedsToday": "Nothing scheduled for today. Open Schedule to see the rest of your week.",
  "home.dosesLeft": "doses left",
  "home.allTaken": "You have taken everything scheduled for today!",
  "home.viewAll": "View all",
  "schedule.pageTitle": "Schedule & medicines",
  "schedule.pageSubtitle": "All medicines and times",
  "schedule.today": "Today's schedule",
  "schedule.weekViewTitle": "This week (by prescribed days)",
  "schedule.viewOnlyOtherDay": "Confirm doses only on this day in the app.",
  "schedule.empty": "Nothing scheduled",
  "schedule.medicineList": "Medicine list",
  "schedule.doseSingular": "dose",
  "schedule.dosePlural": "doses",
  "schedule.medicinesHeadingHint": "Tap Info for full details",
  "scanner.pageTitle": "Medicine scanner",
  "scanner.pageSubtitle": "Barcode for medicine information",
  "profile.pageTitle": "Profile",
  "scheduleCard.confirmTitle": "Confirm: Have you taken this dose?",
  "scheduleCard.confirmNo": "Not yet",
  "scheduleCard.confirmYes": "Yes, I took it",
  "scheduleCard.takenLine": "Taken",
  "medicineList.emptyTitle": "No medicines yet",
  "medicineList.emptyHint": "Your doctor adds medicines to your list after linking and prescribing.",
  "medicineList.remove": "Remove this medicine",
  "medicineList.info": "Info",
  "medicineDialog.generic": "Name",
  "medicineDialog.dosage": "Dosage",
  "medicineDialog.purpose": "What it's for",
  "medicineDialog.precautions": "Precautions",
  "medicineDialog.schedule": "Schedule",
  "medicineDialog.notes": "Notes",
  "register.pendingNote":
    "New doctor accounts stay pending until an admin approves them. Doctors must be activated before they can sign in.",
  "register.activeNote":
    "Patient accounts are activated immediately. You can sign in right after creating your account.",
  "profile.language": "Language",
  "profile.aboutTitle": "About PharSayo",
  "profile.aboutP1":
    "PharSayo helps people understand their medicines, stick to schedules, and stay on track with care—especially older adults and people in community health programs such as barangay health units in the Philippines.",
  "profile.aboutP2":
    "You can scan a medicine barcode for general information. Your personal list and dose times come from your doctor and admin verification.",
  "profile.featuresTitle": "Features",
  "profile.featureScan": "Barcode scan for medicine information",
  "profile.featureRemind": "Scheduled reminders",
  "profile.featureConfirm": "Active confirmation prompts",
  "profile.featureLang": "Information in clear Filipino or English",
  "profile.featureCommunity": "Community health integration",
  "profile.footer": "Capstone Project · Group 8 · Nursing Informatics",
  "profile.editTitle": "Edit profile",
  "profile.username": "Username",
  "profile.fullName": "Full name",
  "profile.phone": "Phone",
  "profile.age": "Age",
  "profile.save": "Save changes",
  "profile.passwordTitle": "Change password",
  "profile.currentPassword": "Current password",
  "profile.newPassword": "New password (min 6 characters)",
  "profile.changePassword": "Update password",
  "scanner.errorNoBarcode": "No barcode detected. Try a clearer photo or type the code below.",
  "scanner.errorNotFound": "Barcode not in our local list. Try scanning the medicine name or typing it below for better results.",
  "scanner.titleScan": "Scan barcode",
  "scanner.uploadBarcodeImage": "Upload barcode photo",
  "scanner.noCameraHint": "No camera? Type the barcode",
  "scanner.lookupDisclaimer":
    "If the product is not a US-labelled item or data is missing, details may be incomplete — always confirm with your doctor or pharmacist.",
  "scanner.searchingTitle": "Looking up medicine…",
  "scanner.searchingHint":
    "Server checks Philippines sources first, then Open Food / Open Products Facts, barcode-list.com, UPCitemdb, then US RxNav/OpenFDA — may take longer on slow networks.",
  "scanner.barcodeCodeLabel": "Barcode / code",
  "scanner.scanAgain": "Scan again",
  "scanner.cameraError": "Could not open the camera. Allow access or type the barcode below.",
  "scanner.imageNoBarcode": "No barcode found in the image.",
  "scanner.imageNoBarcodeDetail": "Try a clearer photo or manual entry.",
  "scanner.tryNameLookupInstead": "Try searching by product name below if the barcode is not in our database.",
  "scanner.addFailed": "Could not add the medicine.",
  "scanner.openCamera": "Open camera",
  "scanner.manualEmpty": "Enter the barcode numbers first.",
  "scanner.lookupErrorGeneric": "Search failed. Please try again.",
  "scanner.toastDoctorOnly": "Your doctor adds medicines and schedules. Show this result to your doctor or pharmacist.",
  "scanner.toastAdded": "Medicine added.",
  "scanner.introBody":
    "1) Read the barcode with the camera or a photo. 2) PharSayo checks Philippine-friendly sources first (app barcode list, Open Food Facts Philippines, then global), then US drug data (RxNav/OpenFDA) for imports. You can also type the product name from the package.",
  "admin.title": "Admin",
  "admin.verifyAccounts": "Verify accounts",
  "admin.tabAccounts": "Accounts",
  "admin.tabLinks": "Doctor ↔ patient",
  "admin.tabSchedules": "Schedules",
  "admin.allAccounts": "All accounts",
  "admin.loading": "Loading…",
  "admin.emptyUsers": "No users.",
  "admin.approve": "Approve",
  "admin.viewVerification": "View ID / document",
  "admin.openVerificationNewTab": "Open in new tab",
  "admin.reject": "Reject",
  "admin.deactivate": "Deactivate",
  "admin.roleStatus": "Role",
  "admin.hintDefaultLogin":
    "After database setup, sign in with username admin and the password you set (change it in production).",
  "admin.linkSection": "Choose a doctor and a patient to link or unlink. Use search if the list is long.",
  "admin.doctorUsername": "Doctor username",
  "admin.patientUsername": "Patient username",
  "admin.linkPair": "Link",
  "admin.unlinkPair": "Unlink",
  "admin.schedulesAll": "All schedules",
  "admin.edit": "Edit",
  "admin.delete": "Delete",
  "admin.time": "Time (HH:MM)",
  "admin.days": "Days (e.g. Mon,Tue,…)",
  "admin.notes": "Notes",
  "admin.patient": "Patient",
  "admin.medicine": "Medicine",
  "admin.doctor": "Doctor",
  "admin.save": "Save",
  "admin.cancel": "Cancel",
  "admin.editUserTitle": "Edit account",
  "admin.editSchedule": "Edit schedule",
  "admin.newPasswordOptional": "New password (optional, min 6 characters)",
  "admin.searchUsersPlaceholder": "Search name, @username, phone…",
  "admin.noUserMatch": "No matching user.",
  "admin.chooseBothLink": "Choose both a doctor and a patient.",
  "admin.invalidAge": "Enter a valid age or leave blank.",
  "admin.deleteAccount": "Delete account",
  "admin.deleteAccountConfirm": "Delete this account permanently?",
  "admin.deleteAccountWarn": "This removes the user and related medicines, schedules, and links. You cannot delete your own account.",
  "doctor.title": "Doctor",
  "doctor.linkPatient": "Link patient",
  "doctor.linkPatientSearch": "Search username or phone…",
  "doctor.linkPatientPickIdle": "Search and select a patient",
  "doctor.linkPatientMinChars": "Type at least 2 characters to search.",
  "doctor.linkHint":
    "The patient account must be active. Search by PharSayo username or phone number, then link.",
  "doctor.prescribeTitle": "Prescribe + schedule",
  "doctor.prescribeHint":
    "Choose patient, medicine, dosage, which days to take it, reminder times, and optional notes. Times use HH:MM (e.g. 08:00, 20:00).",
  "doctor.patient": "Patient",
  "doctor.medName": "Medicine name",
  "doctor.dosage": "Dosage",
  "doctor.times": "Times (comma-separated)",
  "doctor.howOftenDays": "How often (specific days)",
  "doctor.howOftenDaysHint": "e.g. Mon,Tue,Wed,Thu,Fri,Sat,Sun — applies to every reminder time below.",
  "doctor.saveRx": "Save prescription",
  "doctor.linkAction": "Link",
  "doctor.frequencyTextEn": "How often (English, for patient app)",
  "doctor.frequencyTextFil": "How often (Filipino, optional)",
  "doctor.purpose": "Purpose / what it's for",
  "doctor.warnings": "Warnings / precautions",
  "doctor.scheduleNotesLines": "Schedule notes — one line per reminder time",
  "doctor.scheduleNotesLinesHint": "Line 1 applies to the first time you listed, line 2 to the second, etc.",
  "doctor.medNotesExtra": "Extra medicine notes (optional)",
  "doctor.schedulesTitle": "Patient schedules",
  "doctor.schedulesTodayHint":
    "Today’s status uses the server date. “On time” means the patient confirmed within 30 minutes before or after the scheduled dose time. Confirmation times use Philippine Time (PHT, 12-hour AM/PM).",
  "doctor.schedulesSearchPlaceholder": "Search by patient or medicine…",
  "doctor.schedulesNoMatch": "No schedules match your search.",
  "doctor.scheduleEmpty": "No schedule rows yet. Prescribe above or wait for existing data.",
  "doctor.editSchedule": "Edit schedule",
  "doctor.adherenceNotScheduledToday": "Not scheduled today",
  "doctor.adherencePending": "Not confirmed yet",
  "doctor.adherenceTakenOnTime": "Taken on time",
  "doctor.adherenceTakenLate": "Taken (late)",
  "doctor.adherenceTakenUnknown": "Taken (time unknown)",
  "doctor.adherenceMarkedNotTaken": "Marked not taken",
  "doctor.adherenceConfirmedAt": "Patient confirmed at {time}",
  "doctor.refreshSchedules": "Refresh",
  "scanner.lookupCodeButton": "Look up by code",
  "scanner.howItWorksTitle": "How it works",
  "scanner.frameHint": "Point the barcode at the frame — it will be read automatically.",
  "scanner.cancel": "Cancel",
  "scanner.resultTitle": "Scan result",
  "scanner.addToList": "Add to list",
  "scanner.listNotePatient": "Your medicine list and dose times are set by your doctor after your visit.",
  "scanner.dosageHeading": "Dosage",
  "scanner.frequencyHeading": "Typical intake / frequency",
  "scanner.manualBarcodeHelp":
    "Type the digits printed under the barcode (EAN-13, UPC, NDC, etc.). The same server lookup is used as for camera scans.",
  "scanner.barcodeNumberLabel": "Barcode number",
  "scanner.barcodePlaceholder": "e.g. 0030173006585",
  "scanner.nameLookupTitle": "No match? Look up by product name",
  "scanner.nameLookupHelp":
    "If the barcode still fails, type the brand and medicine name on the box (e.g. PCMed Ascorbic Acid 500mg). The server then searches by name (RxNav/OpenFDA labeling, useful for generic ingredients).",
  "scanner.productNameLabel": "Product / medicine name on package",
  "scanner.productNamePlaceholder": "e.g. PCMed Ascorbic Acid 500mg",
  "scanner.nameLookupButton": "Look up by name",
  "scanner.nameLookupEmpty": "Enter the product or medicine name first.",
};

const fil: Record<I18nKey, string> = {
  "nav.home": "Bahay",
  "nav.scan": "I-scan",
  "nav.schedule": "Iskedyul",
  "nav.profile": "Profil",
  "progress.takenLabel": "nainom na",
  "home.bannerTitle": "Reseta mula sa doktor",
  "home.bannerBody":
    "Ang listahan ng gamot at oras ng inom ay itinatakda ng iyong doktor sa PharSayo pagkatapos ng konsultasyon. Maaari mong tingnan ang impormasyon mula sa barcode scan, ngunit hindi awtomatikong idinadagdag sa listahan.",
  "home.scheduleToday": "Iskedyul ngayon",
  "home.noMeds": "Walang naka-iskedyul na gamot",
  "home.noMedsToday": "Walang naka-iskedyul ngayon. Buksan ang Iskedyul para sa ibang araw ng linggo.",
  "home.dosesLeft": "dose ang natitira",
  "home.allTaken": "Lahat ay nainom na ngayon!",
  "home.viewAll": "Tingnan lahat",
  "schedule.pageTitle": "Iskedyul at gamot",
  "schedule.pageSubtitle": "Lahat ng gamot at iskedyul",
  "schedule.today": "Iskedyul ngayon",
  "schedule.weekViewTitle": "Sa linggong ito (ayon sa araw sa reseta)",
  "schedule.viewOnlyOtherDay": "Maaari lamang kumpirmahin ang inom sa araw na ito sa app.",
  "schedule.empty": "Walang naka-iskedyul",
  "schedule.medicineList": "Listahan ng gamot",
  "schedule.doseSingular": "dose",
  "schedule.dosePlural": "na dose",
  "schedule.medicinesHeadingHint": "Pindutin ang Impormasyon para sa buong detalye",
  "scanner.pageTitle": "Medicine scanner",
  "scanner.pageSubtitle": "Barcode para sa impormasyon ng gamot",
  "profile.pageTitle": "Profil",
  "scheduleCard.confirmTitle": "Kumpirmahin: Nainom mo na ba?",
  "scheduleCard.confirmNo": "Hindi pa",
  "scheduleCard.confirmYes": "Oo, nainom ko na",
  "scheduleCard.takenLine": "Nainom na",
  "medicineList.emptyTitle": "Wala pang gamot",
  "medicineList.emptyHint": "Idinadagdag ng doktor ang gamot pagkatapos mag-link at mag-reseta.",
  "medicineList.remove": "Alisin ang gamot na ito",
  "medicineList.info": "Impormasyon",
  "medicineDialog.generic": "Pangalan",
  "medicineDialog.dosage": "Dosage",
  "medicineDialog.purpose": "Para saan",
  "medicineDialog.precautions": "Mga babala",
  "medicineDialog.schedule": "Iskedyul",
  "medicineDialog.notes": "Mga tala",
  "register.pendingNote":
    "Ang bagong doctor account ay pending hanggang aprubahan ng admin. Kailangan i-activate ang doktor bago makapag-sign in.",
  "register.activeNote":
    "Ang mga patient account ay aktibo agad. Maaari ka nang mag-sign in pagkatapos gumawa ng account.",
  "profile.language": "Wika",
  "profile.aboutTitle": "Tungkol sa PharSayo",
  "profile.aboutP1":
    "Ang PharSayo ay isang mobile application na ginawa upang pahusayin ang kaalaman sa gamot, pagsunod sa tamang pag-inom, at pagmamanman — lalo na sa mga matatandang pasyente at mga indibidwal sa ilalim ng community health programs tulad ng Barangay Health Units (BHUs) sa Pilipinas.",
  "profile.aboutP2":
    "Maaaring basahin ang barcode sa gamot para sa pangkalahatang impormasyon; ang personal na listahan at iskedyul ng inom ay mula sa iyong doktor at admin verification.",
  "profile.featuresTitle": "Mga feature",
  "profile.featureScan": "Barcode scan para sa impormasyon ng gamot",
  "profile.featureRemind": "Naka-iskedyul na paalala",
  "profile.featureConfirm": "Active confirmation prompts",
  "profile.featureLang": "Impormasyon sa simpleng Filipino o English",
  "profile.featureCommunity": "Community health integration",
  "profile.footer": "Capstone Project · Group 8 · Nursing Informatics",
  "profile.editTitle": "I-edit ang profile",
  "profile.username": "Username",
  "profile.fullName": "Buong pangalan",
  "profile.phone": "Telepono",
  "profile.age": "Edad",
  "profile.save": "I-save",
  "profile.passwordTitle": "Palitan ang password",
  "profile.currentPassword": "Kasalukuyang password",
  "profile.newPassword": "Bagong password (min 6 character)",
  "profile.changePassword": "I-update ang password",
  "scanner.errorNoBarcode": "Walang barcode na nakita. Subukan ang mas malinaw na kuha o i-type ang code sa ibaba.",
  "scanner.errorNotFound": "Ang barcode ay hindi pa nasa aming listahan. Subukang i-scan ang pangalan ng gamot o i-type ito sa ibaba para mahanap ang impormasyon.",
  "scanner.titleScan": "I-scan ang Barcode",
  "scanner.uploadBarcodeImage": "Mag-upload ng larawan ng barcode",
  "scanner.noCameraHint": "Walang camera? I-type ang barcode",
  "scanner.lookupDisclaimer":
    "Kung hindi US label o kulang ang datos, maaaring hindi lumabas ang detalye — laging kumpirmahin sa doktor o pharmacist.",
  "scanner.searchingTitle": "Hinahanap ang gamot…",
  "scanner.searchingHint":
    "Unang tinitingnan ang mga source na angkop sa Pilipinas, saka Open Food / Open Products Facts, barcode-list.com, UPCitemdb, at huli ang US RxNav/OpenFDA — maaaring tumagal kung mabagal ang network.",
  "scanner.barcodeCodeLabel": "Barcode / code",
  "scanner.scanAgain": "I-scan muli",
  "scanner.cameraError": "Hindi mabuksan ang camera. Pahintulutan ang access o i-type ang barcode sa ibaba.",
  "scanner.imageNoBarcode": "Walang nababasang barcode sa larawan.",
  "scanner.imageNoBarcodeDetail": "Walang nababasang barcode sa larawan. Subukan ang mas malinaw na kuha o i-type ang code.",
  "scanner.tryNameLookupInstead": "Subukang maghanap gamit ang pangalan ng produkto sa ibaba kung wala ang barcode sa aming database.",
  "scanner.addFailed": "Hindi maidagdag ang gamot.",
  "scanner.openCamera": "Buksan ang camera",
  "scanner.manualEmpty": "Maglagay muna ng barcode (mga numero sa ilalim ng barcode).",
  "scanner.lookupErrorGeneric": "Nagkaroon ng problema sa paghahanap. Pakisubukang muli.",
  "scanner.toastDoctorOnly": "Ang doktor ang nagdadagdag ng gamot at iskedyul. Pakikita ang resulta sa doktor o pharmacist.",
  "scanner.toastAdded": "Naidagdag ang gamot.",
  "scanner.introBody":
    "1) Basahin ang barcode gamit ang camera o larawan. 2) Una ang mga source na pabor sa Pilipinas (listahan ng app, Open Food Facts PH, saka global), pagkatapos ang US (RxNav/OpenFDA) para sa mga import. Puwede ring i-type ang pangalan ng produkto sa pakete.",
  "admin.title": "Admin",
  "admin.verifyAccounts": "Beripikahin ang mga account",
  "admin.tabAccounts": "Mga account",
  "admin.tabLinks": "Doktor ↔ pasyente",
  "admin.tabSchedules": "Iskedyul",
  "admin.allAccounts": "Lahat ng account",
  "admin.loading": "Naglo-load…",
  "admin.emptyUsers": "Walang user.",
  "admin.approve": "Aprubahan",
  "admin.viewVerification": "Tingnan ang ID / dokumento",
  "admin.openVerificationNewTab": "Buksan sa bagong tab",
  "admin.reject": "Tanggihan",
  "admin.deactivate": "I-deactivate",
  "admin.roleStatus": "Role",
  "admin.hintDefaultLogin":
    "Pagkatapos ng database setup, mag-sign in gamit ang username na admin at ang password na itinakda mo (palitan sa produksyon).",
  "admin.linkSection": "Pumili ng doktor at pasyente para i-link o i-unlink. Gamitin ang search kung marami ang listahan.",
  "admin.doctorUsername": "Username ng doktor",
  "admin.patientUsername": "Username ng pasyente",
  "admin.linkPair": "I-link",
  "admin.unlinkPair": "I-unlink",
  "admin.schedulesAll": "Lahat ng iskedyul",
  "admin.edit": "I-edit",
  "admin.delete": "Burahin",
  "admin.time": "Oras (HH:MM)",
  "admin.days": "Araw (hal. Mon,Tue,…)",
  "admin.notes": "Mga tala",
  "admin.patient": "Pasyente",
  "admin.medicine": "Gamot",
  "admin.doctor": "Doktor",
  "admin.save": "I-save",
  "admin.cancel": "Kanselahin",
  "admin.editUserTitle": "I-edit ang account",
  "admin.editSchedule": "I-edit ang iskedyul",
  "admin.newPasswordOptional": "Bagong password (opsyonal, min 6 character)",
  "admin.searchUsersPlaceholder": "Maghanap ng pangalan, @username, telepono…",
  "admin.noUserMatch": "Walang tumugmang user.",
  "admin.chooseBothLink": "Pumili ng doktor at pasyente.",
  "admin.invalidAge": "Maglagay ng wastong edad o iwanang blangko.",
  "admin.deleteAccount": "Burahin ang account",
  "admin.deleteAccountConfirm": "Permanenteng burahin ang account na ito?",
  "admin.deleteAccountWarn":
    "Maaalis ang user at kaugnay na gamot, iskedyul, at link. Hindi mabubura ang sarili mong account.",
  "doctor.title": "Doktor",
  "doctor.linkPatient": "I-link ang pasyente",
  "doctor.linkPatientSearch": "Maghanap ng username o telepono…",
  "doctor.linkPatientPickIdle": "Maghanap at pumili ng pasyente",
  "doctor.linkPatientMinChars": "Mag-type ng hindi bababa sa 2 character para maghanap.",
  "doctor.linkHint":
    "Dapat aktibo ang account ng pasyente. Maghanap gamit ang username o numero ng telepono sa PharSayo, saka i-link.",
  "doctor.prescribeTitle": "Mag-reseta + iskedyul",
  "doctor.prescribeHint":
    "Pumili ng pasyente, gamot, dosis, araw ng pag-inom, mga oras ng paalala, at opsyonal na tala. Gumamit ng HH:MM (hal. 08:00, 20:00).",
  "doctor.patient": "Pasyente",
  "doctor.medName": "Pangalan ng gamot",
  "doctor.dosage": "Dosis",
  "doctor.times": "Mga oras (hiwalay ng kuwit)",
  "doctor.howOftenDays": "Gaano kadalas (tiyak na araw)",
  "doctor.howOftenDaysHint": "hal. Mon,Tue,Wed,Thu,Fri,Sat,Sun — nalalapat sa bawat oras ng paalala sa ibaba.",
  "doctor.saveRx": "I-save ang reseta",
  "doctor.linkAction": "I-link",
  "doctor.frequencyTextEn": "Gaano kadalas (English, sa app ng pasyente)",
  "doctor.frequencyTextFil": "Gaano kadalas (Filipino, opsyonal)",
  "doctor.purpose": "Layunin / para saan",
  "doctor.warnings": "Babala / pag-iingat",
  "doctor.scheduleNotesLines": "Mga tala sa iskedyul — isang linya bawat oras ng paalala",
  "doctor.scheduleNotesLinesHint": "Ang linya 1 ay para sa unang oras sa listahan, linya 2 sa ikalawa, atbp.",
  "doctor.medNotesExtra": "Karagdagang tala sa gamot (opsyonal)",
  "doctor.schedulesTitle": "Iskedyul ng mga pasyente",
  "doctor.schedulesTodayHint":
    "Ang status ngayong araw ay batay sa petsa ng server. Ang “nasakto sa oras” ay kung kinumpirma sa loob ng 30 minuto bago o pagkatapos ng naka-iskedyul na oras ng inom. Ang oras ng kumpirmasyon ay Philippine Time / PHT (12-oras, AM/PM).",
  "doctor.schedulesSearchPlaceholder": "Maghanap ayon sa pasyente o gamot…",
  "doctor.schedulesNoMatch": "Walang tumutugma sa iyong hinahanap.",
  "doctor.scheduleEmpty": "Wala pang iskedyul. Mag-reseta sa itaas o hintayin ang datos.",
  "doctor.editSchedule": "I-edit ang iskedyul",
  "doctor.adherenceNotScheduledToday": "Hindi naka-iskedyul ngayon",
  "doctor.adherencePending": "Hindi pa kinukumpirma",
  "doctor.adherenceTakenOnTime": "Nainom sa tamang oras",
  "doctor.adherenceTakenLate": "Nainom (huli)",
  "doctor.adherenceTakenUnknown": "Nainom (di tiyak ang oras)",
  "doctor.adherenceMarkedNotTaken": "Minarkahang hindi nainom",
  "doctor.adherenceConfirmedAt": "Kinumpirma ng pasyente nang {time}",
  "doctor.refreshSchedules": "I-refresh",
  "scanner.lookupCodeButton": "Hanapin gamit ang code",
  "scanner.howItWorksTitle": "Paano gumagana",
  "scanner.frameHint": "Itutok ang barcode sa frame — awtomatik itong babasahin.",
  "scanner.cancel": "Kanselahin",
  "scanner.resultTitle": "Resulta ng scan",
  "scanner.addToList": "Idagdag sa listahan",
  "scanner.listNotePatient": "Ang listahan ng gamot at oras ng inom ay itinatakda ng iyong doktor pagkatapos ng konsultasyon.",
  "scanner.dosageHeading": "Dosis at pag-inom / Dosage",
  "scanner.frequencyHeading": "Inirerekomendang dalas / Intake",
  "scanner.manualBarcodeHelp":
    "Ilagay ang mga digit na nakikita sa ilalim ng barcode (EAN-13, UPC, NDC, atbp.). Gagamitin ang parehong lookup sa server.",
  "scanner.barcodeNumberLabel": "Numero ng barcode",
  "scanner.barcodePlaceholder": "hal. 0030173006585",
  "scanner.nameLookupTitle": "Walang nahanap? Hanapin sa pangalan ng produkto",
  "scanner.nameLookupHelp":
    "Kung hindi pa rin humana ang barcode, i-type ang brand at pangalan ng gamot (hal. PCMed Ascorbic Acid 500mg). Hahanapin ayon sa pangalan sa RxNav/OpenFDA (karaniwang may datos ang generic na sangkap).",
  "scanner.productNameLabel": "Pangalan ng produkto / gamot sa pakete",
  "scanner.productNamePlaceholder": "hal. PCMed Ascorbic Acid 500mg",
  "scanner.nameLookupButton": "Hanapin sa pangalan",
  "scanner.nameLookupEmpty": "Maglagay muna ng pangalan ng produkto o gamot.",
};

export function t(lang: Lang, key: I18nKey): string {
  return lang === "en" ? en[key] : fil[key];
}
