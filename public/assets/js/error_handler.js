const confirmation = document.querySelector('.confirmation');
if (confirmation) {
    setTimeout(() => {
        confirmation.classList.add('hide');
    }, 3000);
}

const error = document.querySelector('.error');
if (error) {
    setTimeout(() => {
        error.classList.add('hide');
    }, 3000);
}