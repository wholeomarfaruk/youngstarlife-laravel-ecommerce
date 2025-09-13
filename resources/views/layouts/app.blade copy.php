<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Young Star - Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <!-- lightGallery css -->
  <link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css"
/>
  <!-- external css -->
  <link rel="stylesheet" href="{{asset('css/style.css')}}" />
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
  <!-- ===== Fontawesome CDN Link ===== -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <script src="https://kit.fontawesome.com/3c9b731536.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnify/2.3.3/css/magnify.min.css" integrity="sha512-wzhF4/lKJ2Nc8mKHNzoFP4JZsnTcBOUUBT+lWPcs07mz6lK3NpMH1NKCKDMarjaw8gcYnSBNjjllN4kVbKedbw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  @stack('styles')
  {{-- @foreach ($analytics as $analytic)

  {{ $analytic->code ?? '' }}

  @endforeach --}}
</head>
<body>
      <!--header start-->
  <nav class="navbar  sticky-top navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
      <a class="navbar-brand text-success fw-bolder" href="#"><i class="fa-regular fa-star me-1"></i>YOUNGSTAR</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link navbar-link" aria-current="page" href="#delivery-box">পরিচিতি</a>
          </li>
          <li class="nav-item">
            <a class="nav-link navbar-link" href="#difference">পার্থক্য</a>
          </li>

          <li class="nav-item">
            <a class="nav-link navbar-link" href="#feature" aria-disabled="true">ফিচারস</a>
          </li>
          <li class="nav-item">
            <a class="nav-link navbar-link" href="#review" aria-disabled="true">রিভিউ</a>
          </li>
          <li class="nav-item">
            <a class="nav-link navbar-link" href="#question" aria-disabled="true">জিজ্ঞাসা</a>
          </li>
          <li class="nav-item">
            <a class="nav-link navbar-link" href="#gallery" aria-disabled="true">গ্যালারী</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link navbar-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              যোগাযোগ
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#">ফেসবুক</a></li>
              <li><a class="dropdown-item" href="#"> ইনস্টাগ্রাম</a></li>
              <li><a class="dropdown-item" href="#">হোয়াটসঅ্যাপ</a></li>
            </ul>
          </li>
        </ul>
        <a href="#order" class="">
          <button class="btn nav-button" type="submit">
            <i class="fa-solid fa-bag-shopping pe-2"></i>অর্ডার করুন
          </button>
        </a>
      </div>
    </div>
  </nav>
  <!-- header End -->

      @yield('content')

      <!-- Footer -->
  <footer class="section-footer mt-5">
    <div class="container">
      <div class="row footer-content">
        <div class="col-md">
          <a class="footer-brand text-decoration-none text-success fw-bolder fs-4" href="#">
            <i class="fa-regular fa-star me-1"></i>YOUNGSTAR</a>
          <p class="footer-text">© ২০২৫ YoungStar <br>

আমরা নিয়মিত লঞ্চ করি নতুন, স্টাইলিশ ও প্রিমিয়াম কোয়ালিটির প্যান্ট কালেকশন।
আপনার স্টাইল ও আরামের নিশ্চয়তা, শুধুমাত্র YoungStar-এ।</p>
        </div>
        <div class="col-md">
          <h6 class="footer-titel">আমাদের ঠিকানা</h6>
          <p class="footer-text-contact"><i class="fa-solid fa-location-arrow me-2"></i>ডগাইর, ডেমরা, ঢাকা ১৩৬১</p>
          <p class="footer-text-contact"><i class="fa-solid fa-envelope me-2"></i>youngstar@gmail.com</p>
          <p class="footer-text-contact"><i class="fa-solid fa-headset me-2"></i>+8801234567899</p>
        </div>
        <div class="col-md">
          <h6 class="footer-titel">সোশ্যাল মিডিয়া</h6>
          <div class="social-icon d-flex gap-2">
            <a href="" class="icon-group"><i class="fa-solid fa-user-group"></i></a>
            <a href="" class="icon-facebook"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="" class="icon-messenger"><i class="fa-brands fa-facebook-messenger"></i></a>
            <a href="" class="icon-phone"><i class="fa-solid fa-phone-volume"></i></a>
            <a href="" class="icon-whatsapp"><i class="fa-brands fa-whatsapp"></i></a>
            <a href="" class="icon-mail"><i class="fa-regular fa-envelope"></i></a>
          </div>
        </div>
      </div>
      <hr class="divider">
      <div class="section-copyright d-flex justify-content-between">
        <p class="footer-text"><i class="fa-regular fa-copyright"></i>2020-2024 YoungStar. All rights reserved.</p>
        <p class="footer-text">Developed by Md Omar Faruk</p>
      </div>
    </div>
  </footer>
      <!--jquery min-->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- Swiper JS -->
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <!-- boostrap js cdn -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/magnify/2.3.3/js/jquery.magnify.min.js" integrity="sha512-YKxHqn7D0M5knQJO2xKHZpCfZ+/Ta7qpEHgADN+AkY2U2Y4JJtlCEHzKWV5ZE87vZR3ipdzNJ4U/sfjIaoHMfw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- external js -->

  <script src="{{asset('js/script.js')}}"></script>

  @stack('scripts')

</body>
</html>
