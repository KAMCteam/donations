const notes = document.querySelectorAll('.note');
const overlay = document.getElementById('overlay');
const noteBlock = document.getElementById('pop-up');
const noteContent = document.getElementById('pop-up-content');

notes.forEach(note => {
    note.addEventListener('click', (e) => {
        e.stopPropagation();
        noteBlock.style.display = 'block';
        overlay.style.display = 'block';
        
        noteContent.textContent = note.dataset.note;
    });
});

function closePopUp() {
    noteBlock.style.display = 'none';
    overlay.style.display = 'none';
}