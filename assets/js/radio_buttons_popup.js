// Recipient / Donor Box
const recipientBox = document.getElementById('recipient-box');
const donorBox = document.getElementById('donor-box');
// Recipient / Donor Radio button
const recipientRadio = document.getElementById('recipient-radio');    
const donorRadio = document.getElementById('donor-radio');
// Recipient / Donor Div that has inputs
const recipientDiv = document.getElementById('recipient');
const donorDiv = document.getElementById('donor');
// The choose pair input
const crm = document.getElementById('choose-recipient-mrn');
const cdm = document.getElementById('choose-donor-mrn');
// Recipient Div that has inputs
recipientBox.addEventListener('click', () => {
    recipientDiv.classList.remove('nodis');
    donorDiv.classList.add('nodis');
    recipientRadio.checked = true;
    donorRadio.checked = false;
    try {crm.value = null;} catch(error) {console.error("An error occurred:", error.message)}

    var rItems = document.querySelectorAll('.recipient-hidden');
    var dItems = document.querySelectorAll('.donor-hidden');
    for (var item of rItems) {
        item.required = true;
    }

    for (var item of dItems) {
        item.required = false;
    }
});
// Donor Div that has inputs
donorBox.addEventListener('click', () => {
    recipientDiv.classList.add('nodis');
    donorDiv.classList.remove('nodis');
    recipientRadio.checked = false;
    donorRadio.checked = true;
    try {cdm.value = null;} catch(error) {console.error("An error occurred:", error.message)}

    var rItems = document.querySelectorAll('.recipient-hidden');
    var dItems = document.querySelectorAll('.donor-hidden');
    for (var item of rItems) {
        item.required = false;
    }

    for (var item of dItems) {
        item.required = true;
    }

    donorDivTrue.classList.add('nodis');
    // donorDivFalse.classList.add('nodis');
    var rItems2 = document.querySelectorAll('.donor-true-hidden');
    // var dItems2 = document.querySelectorAll('.donor-false-hidden');
    for (var item of rItems2) {
        item.required = false;
    }

    // for (var item of dItems2) {
    //     item.required = false;
    // }
});

// Donor true / false Box
const donorBoxTrue = document.getElementById('donor-box-true');
const donorBoxFalse = document.getElementById('donor-box-false');
// Donor true / false Radio button
const donorRadioTrue = document.getElementById('donor-radio-true');
const donorRadioFalse = document.getElementById('donor-radio-false');
// Donor true / false Div that has inputs
const donorDivTrue = document.getElementById('donor-true');
// const donorDivFalse = document.getElementById('donor-false');
// Donor true Div that has inputs
donorBoxTrue.addEventListener('click', () => {
    donorDivTrue.classList.remove('nodis');
    // donorDivFalse.classList.add('nodis');
    donorRadioTrue.checked = true;
    donorRadioFalse.checked = false;

    var rItems = document.querySelectorAll('.donor-true-hidden');
    // var dItems = document.querySelectorAll('.donor-false-hidden');
    for (var item of rItems) {
        item.required = true;
    }

    // for (var item of dItems) {
    //     item.required = false;
    // }
});
// Donor false Div that has inputs
donorBoxFalse.addEventListener('click', () => {
    donorDivTrue.classList.add('nodis');
    // donorDivFalse.classList.remove('nodis');
    donorRadioTrue.checked = false;
    donorRadioFalse.checked = true;

    var rItems = document.querySelectorAll('.donor-true-hidden');
    // var dItems = document.querySelectorAll('.donor-false-hidden');
    for (var item of rItems) {
        item.required = false;
    }

    // for (var item of dItems) {
    //     item.required = true;
    // }
});

function initialCheck() {
    if (recipientRadio.checked == true) {
        recipientDiv.classList.remove('nodis');
        donorDiv.classList.add('nodis');
        recipientRadio.checked = true;
        donorRadio.checked = false;

        var rItems = document.querySelectorAll('.recipient-hidden');
        var dItems = document.querySelectorAll('.donor-hidden');
        for (var item of rItems) {
            item.required = true;
        }

        for (var item of dItems) {
            item.required = false;
        }

    } else if (donorRadio.checked == true) {
        recipientDiv.classList.add('nodis');
        donorDiv.classList.remove('nodis');
        recipientRadio.checked = false;
        donorRadio.checked = true;

        var rItems = document.querySelectorAll('.recipient-hidden');
        var dItems = document.querySelectorAll('.donor-hidden');
        for (var item of rItems) {
            item.required = false;
        }

        for (var item of dItems) {
            item.required = true;
        }

        donorDivTrue.classList.add('nodis');
        // donorDivFalse.classList.add('nodis');
        var rItems2 = document.querySelectorAll('.donor-true-hidden');
        // var dItems2 = document.querySelectorAll('.donor-false-hidden');
        for (var item of rItems2) {
            item.required = false;
        }

        // for (var item of dItems2) {
        //     item.required = false;
        // }
    }

    if (donorRadioTrue.checked == true) {
        donorDivTrue.classList.remove('nodis');
        // donorDivFalse.classList.add('nodis');
        donorRadioTrue.checked = true;
        donorRadioFalse.checked = false;

        var rItems = document.querySelectorAll('.donor-true-hidden');
        // var dItems = document.querySelectorAll('.donor-false-hidden');
        for (var item of rItems) {
            item.required = true;
        }

        // for (var item of dItems) {
        //     item.required = false;
        // }
    } else if (donorRadioFalse.checked == true) {
        donorDivTrue.classList.add('nodis');
        // donorDivFalse.classList.remove('nodis');
        donorRadioTrue.checked = false;
        donorRadioFalse.checked = true;

        var rItems = document.querySelectorAll('.donor-true-hidden');
        // var dItems = document.querySelectorAll('.donor-false-hidden');
        for (var item of rItems) {
            item.required = false;
        }

        // for (var item of dItems) {
        //     item.required = true;
        // }
    }
}

initialCheck();