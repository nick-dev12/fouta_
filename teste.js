// let observer = new IntersectionObserver((entries=>{
//     entries.forEach((entry)=>{
//         if(entry.isIntersecting){
//             entry.target.classList.add('show')
//         }else{
//             entry.target.classList.remove('show')
//         }
//     })
// }))

// let box_hiden = document.querySelectorAll('.box_hiden')
// box_hiden.forEach((el)=> observer.observe(el));
// Sélectionne les éléments 
const boxes = document.querySelectorAll('.box_hiden'); 

// Détecte le scroll
window.addEventListener('scroll', checkVisibility);

function checkVisibility() {

  // Boucle sur chaque élément
  boxes.forEach(box => {

    // Récupère la position
    const rect = box.getBoundingClientRect();

    // Vérifie si l'élément est visible 
    if(rect.top < window.innerHeight && rect.bottom > 0) {
      box.classList.add('show');
    } else {
       box.classList.remove('show');
    }

  });

}