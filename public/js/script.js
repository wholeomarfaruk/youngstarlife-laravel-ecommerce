var swiper = new Swiper(".mySwiper", {
    spaceBetween: 30,
    grabCursor: true,
    loop: true,
    // Pagination
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
    },
    // Next and previous navigation
    navigation: {
      nextEl: ".swiper-button-next",
      prevEl: ".swiper-button-prev",
    },
    // Responsive breakpoints
    breakpoints: {
      0: {
        slidesPerView: 1
      },
      768: {
        slidesPerView: 2
      },
      1024: {
        slidesPerView: 3
      }
    }
  });