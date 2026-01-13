<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>СНТ Инициативный</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">СНТ <span class="text-primary">Инициативный</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#novosti">Новости</a></li>
                    <li class="nav-item"><a class="nav-link" href="#obyavleniya">Объявления</a></li>
                    <li class="nav-item"><a class="nav-link" href="#statyi">Статьи</a></li>
                    <li class="nav-item"><a class="nav-link" href="#dokumenty">Документы</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- SNT INITIATIVE: HOME LAYOUT (Bootstrap 5 + SOW) -->
<div class="snt-home">

  <!-- HERO -->
  <section class="snt-hero">
    <div class="snt-hero__bg" style="background-image:url('assets/img/hero.jpg');"></div>
    <div class="snt-hero__overlay"></div>

    <div class="container position-relative">
      <div class="row align-items-center g-4">
        <div class="col-12 col-lg-6">
          <h1 class="snt-hero__title mb-3">
            Добро пожаловать<br>в СНТ "Инициативный"!
          </h1>
          <p class="snt-hero__subtitle mb-4">
            Новости и информация для садоводов
          </p>

          <a href="/news/" class="btn btn-success snt-btn">
            Узнать больше
          </a>
        </div>
        <div class="col-12 col-lg-6 d-none d-lg-block">
          <!-- пустая колонка — под картинку справа в фоне -->
        </div>
      </div>
    </div>
  </section>


  <!-- 4 BLOCKS -->
  <section class="py-5">
    <div class="container">
      <div class="row g-4">

        <!-- NEWS -->
        <div class="col-12 col-lg-6">
          <div class="card snt-card h-100">
            <div class="snt-card__media" style="background-image:url('assets/img/news.jpg');"></div>

            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">Новости</h2>
              </div>

              <div class="list-group list-group-flush snt-list">
                <a href="/news/1" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Собрание садоводов</span>
                  <span class="snt-list__meta">15 апреля 2024</span>
                </a>
                <a href="/news/2" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Плановые работы на электросетях</span>
                  <span class="snt-list__meta">10 апреля 2024</span>
                </a>
              </div>

              <div class="pt-3 text-end">
                <a href="/news/" class="snt-link">
                  Все новости <span class="snt-link__arrow">›</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- DOCS -->
        <div class="col-12 col-lg-6">
          <div class="card snt-card h-100">
            <div class="snt-card__media" style="background-image:url('assets/img/docs.jpg');"></div>

            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">Документы</h2>
              </div>

              <div class="list-group list-group-flush snt-list">
                <a href="/docs/rules" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Правила СНТ</span>
                  <span class="snt-list__meta">PDF</span>
                </a>
                <a href="/docs/forms" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Образцы заявлений</span>
                  <span class="snt-list__meta">DOCX</span>
                </a>
              </div>

              <div class="pt-3 text-end">
                <a href="/docs/" class="snt-link">
                  Все документы <span class="snt-link__arrow">›</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- ANNOUNCEMENTS -->
        <div class="col-12 col-lg-6">
          <div class="card snt-card h-100">
            <div class="snt-card__media" style="background-image:url('assets/img/ads.jpg');"></div>

            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">Объявления</h2>
              </div>

              <div class="list-group list-group-flush snt-list">
                <a href="/announcements/1" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Продам дачный участок</span>
                  <span class="snt-list__meta">›</span>
                </a>
                <a href="/announcements/2" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Требуется электрик</span>
                  <span class="snt-list__meta">›</span>
                </a>
              </div>

              <div class="pt-3 text-end">
                <a href="/announcements/" class="snt-link">
                  Все объявления <span class="snt-link__arrow">›</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- ARTICLES -->
        <div class="col-12 col-lg-6">
          <div class="card snt-card h-100">
            <div class="snt-card__media" style="background-image:url('assets/img/articles.jpg');"></div>

            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">Статьи</h2>
              </div>

              <div class="list-group list-group-flush snt-list">
                <a href="/articles/1" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Полив и уход за садом</span>
                  <span class="snt-list__meta">›</span>
                </a>
                <a href="/articles/2" class="list-group-item snt-list__item">
                  <span class="snt-list__title">Как бороться с сорняками</span>
                  <span class="snt-list__meta">›</span>
                </a>
              </div>

              <div class="pt-3 text-end">
                <a href="/articles/" class="snt-link">
                  Все статьи <span class="snt-link__arrow">›</span>
                </a>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /row -->
    </div><!-- /container -->
  </section>


  <!-- ABOUT -->
  <section class="pb-5">
    <div class="container">
      <div class="card snt-card snt-about">
        <div class="row g-0 align-items-stretch">
          <div class="col-12 col-lg-6">
            <div class="snt-about__media" style="background-image:url('assets/img/about.jpg');"></div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card-body snt-about__body">
              <h2 class="h3 mb-2">О нас</h2>
              <div class="text-muted mb-3">О нашем СНТ</div>
              <p class="mb-4">
                Мы объединение садоводов, создающее комфортные условия
                для отдыха и ведения садового хозяйства.
              </p>

              <a href="/about/" class="btn btn-success snt-btn">
                Подробнее
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- FOOTER -->
  <footer class="snt-footer">
    <div class="container py-4">
      <div class="row g-4">

        <div class="col-12 col-lg-4">
          <div class="snt-footer__title">Контакты</div>
          <div class="snt-footer__item">Телефон: +7 (900) 123-45-67</div>
          <div class="snt-footer__item">Email: info@snt-initiative.ru</div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="snt-footer__title">Полезные ссылки</div>
          <a class="snt-footer__link" href="/gallery/">Галерея</a>
          <a class="snt-footer__link" href="/forum/">Форум</a>
          <a class="snt-footer__link" href="/tips/">Полезные советы</a>
        </div>

        <div class="col-12 col-lg-4">
          <div class="snt-footer__title">Мы в соцсетях</div>
          <div class="d-flex gap-2">
            <a class="snt-soc" href="#" aria-label="VK">vk</a>
            <a class="snt-soc" href="#" aria-label="Facebook">f</a>
            <a class="snt-soc" href="#" aria-label="WhatsApp">wa</a>
          </div>
        </div>

      </div>
    </div>

    <div class="snt-footer__bottom">
      <div class="container py-3 text-center">
        © 2024 СНТ "Инициативный". Все права защищены.
      </div>
    </div>
  </footer>

</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
