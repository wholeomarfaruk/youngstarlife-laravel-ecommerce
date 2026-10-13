<style>
    /* Product page — design tokens from "Product section redesign" handoff (ink #262826 · border #e4e4e0 · input #d6d6d1 · page #f4f4f2 · soft #f7f7f5) */
    body { background: #f4f4f2; }
    #Content-body { padding-top: 0 !important; }
    [x-cloak] { display: none !important; }
    .pp { max-width: 1200px; margin: 0 auto; padding: 16px 20px 32px; font-family: 'Hind Siliguri', system-ui, sans-serif; color: #262826; -webkit-font-smoothing: antialiased; }
    .pp *, .pp *::before, .pp *::after { box-sizing: border-box; }
    .pp button, .pp input, .pp textarea { font-family: inherit; }
    .pp a { color: #3f423f; text-decoration: none; }
    .pp a:hover { color: #262826; text-decoration: underline; }
    .pp-crumb { font-size: 13px; color: #7a7d7a; display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
    .pp-crumb-now { color: #3f423f; }
    .pp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 440px), 1fr)); gap: 32px; align-items: start; }

    /* gallery */
    .pp-gallery { position: sticky; top: 76px; display: flex; flex-direction: column; gap: 12px; min-width: 0; }
    .pp-main { position: relative; aspect-ratio: 4/5; border-radius: 14px; overflow: hidden; background: #ece9e0; }
    .pp-main img { width: 100%; height: 100%; object-fit: cover; display: block; cursor: zoom-in; }
    .pp-badge-off { position: absolute; top: 14px; left: 14px; background: #c92a2a; color: #fff; font-weight: 700; font-size: 14px; padding: 4px 10px; border-radius: 20px; }
    .pp-badge-combo { position: absolute; top: 14px; right: 14px; background: #262826; color: #fff; font-weight: 600; font-size: 13px; padding: 4px 10px; border-radius: 20px; }
    .pp-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border-radius: 50%; border: 0; background: rgba(255,255,255,.9); cursor: pointer; font-size: 18px; color: #262826; line-height: 1; }
    .pp-prev { left: 12px; } .pp-next { right: 12px; }
    .pp-thumbs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .pp-thumbs button { aspect-ratio: 1; border-radius: 10px; overflow: hidden; padding: 0; cursor: pointer; background: #ece9e0; border: 2px solid transparent; }
    .pp-thumbs button.is-on { border-color: #262826; }
    .pp-thumbs img { width: 100%; height: 100%; object-fit: cover; display: block; }

    /* info column */
    .pp-info { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
    .pp-stack-2, .pp-stack-6, .pp-stack-8, .pp-stack-10, .pp-stack-12, .pp-stack-14, .pp-stack-16 { display: flex; flex-direction: column; }
    .pp-stack-2 { gap: 2px; } .pp-stack-6 { gap: 6px; } .pp-stack-8 { gap: 8px; } .pp-stack-10 { gap: 10px; } .pp-stack-12 { gap: 12px; } .pp-stack-14 { gap: 14px; } .pp-stack-16 { gap: 16px; }
    .pp-min0 { min-width: 0; } .pp-mt12 { margin-top: 12px; }
    .pp-title { margin: 0; font-size: 30px; line-height: 1.25; font-weight: 700; text-wrap: pretty; color: #262826; }
    .pp-price-row { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .pp-price { font-size: 32px; font-weight: 700; }
    .pp-old { font-size: 18px; color: #8a8d8a; text-decoration: line-through; }
    .pp-save { font-size: 14px; font-weight: 600; color: #c92a2a; background: #fdecec; padding: 2px 8px; border-radius: 6px; }
    .pp-short { margin: 0; font-size: 15px; line-height: 1.6; color: #4a4d4a; }
    .pp-trust { display: flex; gap: 16px; flex-wrap: wrap; font-size: 14px; color: #4a4d4a; }
    .pp-trust-stock { display: flex; align-items: center; gap: 6px; }
    .pp-dot { width: 8px; height: 8px; border-radius: 50%; } .pp-dot.is-ok { background: #2f9e44; } .pp-dot.is-out { background: #c92a2a; }
    .pp a.pp-wa { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; background: #eaf7ef; color: #1d5e36; font-size: 15px; text-decoration: none; }
    .pp a.pp-wa svg { flex: none; }
    .pp-red { color: #c92a2a; }
    .pp-card { background: #fff; border: 1px solid #e4e4e0; border-radius: 14px; padding: 18px; }
    .pp-card-text { padding: 14px 18px; }
    .pp-row-between { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .pp-wrap { flex-wrap: wrap; }
    .pp-label { font-weight: 600; font-size: 16px; } .pp-label-sm { font-size: 14px; font-weight: 600; }
    .pp-muted { font-weight: 400; color: #7a7d7a; } .pp-muted2 { color: #4a4d4a; } .pp-muted3 { color: #6b6e6b; }
    .pp-link { border: 0; background: none; color: #3f423f; text-decoration: underline; cursor: pointer; font-size: 14px; padding: 0; }

    /* size chips */
    .pp-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .pp-chip { min-width: 56px; height: 46px; padding: 0 12px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; background: #fff; color: #262826; border: 1.5px solid #d6d6d1; transition: background .12s, color .12s, border-color .12s; }
    .pp-chip:hover:not(:disabled) { border-color: #262826; }
    .pp-chip.is-on { background: #262826; border-color: #262826; color: #fff; }
    .pp-chip:disabled { background: #f4f4f2; color: #b0b2b0; text-decoration: line-through; cursor: not-allowed; }
    .pp-chips-sm { gap: 6px; } .pp-chips-sm .pp-chip { min-width: 50px; height: 42px; border-radius: 9px; font-size: 15px; padding: 0 10px; }
    .pp-check { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4a4d4a; cursor: pointer; margin: 0; }
    .pp-check input { width: 18px; height: 18px; accent-color: #262826; }

    /* combo rows */
    .pp-combo { display: grid; grid-template-columns: 96px minmax(0, 1fr); gap: 14px; padding: 12px; border-radius: 12px; background: #f7f7f5; align-items: start; }
    .pp-combo-img { position: relative; width: 96px; aspect-ratio: 3/4; border-radius: 10px; overflow: hidden; background: #ece9e0; border: 2px solid #d6d6d1; }
    .pp-combo-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pp-combo-color { position: absolute; left: 0; right: 0; bottom: 0; background: #262826; color: #fff; font-size: 11px; text-align: center; padding: 2px 0; }
    .pp-combo-head { display: flex; align-items: center; gap: 8px; font-size: 15px; flex-wrap: wrap; }
    .pp-swatch { width: 18px; height: 18px; border-radius: 50%; border: 1px solid rgba(0,0,0,.15); flex: none; }
    .pp-swatch-sm { width: 14px; height: 14px; }
    .pp-pill { font-size: 12px; font-weight: 600; color: #6b6e6b; background: #ecece8; padding: 1px 8px; border-radius: 10px; }
    .pp-combo-picked { margin-left: auto; color: #4a4d4a; font-size: 14px; }
    .pp-combo-name { font-size: 16px; font-weight: 600; line-height: 1.3; text-wrap: pretty; }
    .pp-combo-line { display: flex; align-items: center; gap: 10px; font-size: 15px; }

    /* size chart */
    .pp-chart { border: 1px solid #e4e4e0; border-radius: 10px; overflow: hidden; }
    .pp-chart-scroll { overflow-x: auto; }
    .pp-chart table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: center; min-width: 480px; margin: 0; }
    .pp-chart th { background: #f4f4f2; padding: 8px 6px; font-weight: 600; }
    .pp-chart td { padding: 8px 6px; border-top: 1px solid #eee; }
    .pp-chart tr.is-on td { background: #f4f1e8; }
    .pp-chart-note { font-size: 12px; color: #7a7d7a; padding: 6px 10px; }
    .pp-chart-img { width: 100%; display: block; }

    /* qty + buttons */
    .pp-qty { display: flex; align-items: center; border: 1.5px solid #d6d6d1; border-radius: 10px; overflow: hidden; }
    .pp-qty button { width: 46px; height: 44px; border: 0; background: #fff; font-size: 20px; cursor: pointer; color: #262826; }
    .pp-qty button:disabled { color: #b0b2b0; cursor: default; }
    .pp-qty span { width: 48px; text-align: center; font-size: 17px; font-weight: 600; }
    .pp-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .pp-btn, .pp-btn-outline { height: 52px; border-radius: 10px; font-size: 17px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background .12s; }
    .pp-btn { border: 0; background: #262826; color: #fff; font-weight: 700; }
    .pp-btn:hover:not(:disabled) { background: #3f423f; }
    .pp-btn-outline { border: 1.5px solid #262826; background: #fff; color: #262826; font-weight: 600; }
    .pp-btn-outline:hover:not(:disabled) { background: #f4f4f2; }
    .pp-btn:disabled, .pp-btn-outline:disabled { opacity: .55; cursor: not-allowed; }
    .pp-btn-lg { height: 54px; font-size: 18px; width: 100%; }
    .pp-err { color: #c92a2a; font-size: 14px; }

    /* order form */
    .pp-h2 { font-size: 20px; font-weight: 700; } .pp-h3 { font-size: 17px; font-weight: 600; } .pp-sub { font-size: 14px; color: #6b6e6b; }
    .pp-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .pp-field { display: flex; flex-direction: column; gap: 6px; font-size: 14px; font-weight: 600; margin: 0; }
    .pp-field input, .pp-field textarea { height: 46px; border: 1.5px solid #d6d6d1; border-radius: 10px; padding: 0 12px; font-size: 16px; font-weight: 400; outline: none; color: #262826; background: #fff; width: 100%; }
    .pp-field textarea { height: auto; padding: 10px 12px; resize: vertical; }
    .pp-field input:focus, .pp-field textarea:focus { border-color: #262826; }
    .pp-field .is-bad { border-color: #c92a2a; }
    .pp-areas { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 8px; }
    .pp-area { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; cursor: pointer; text-align: left; font-size: 14px; background: #fff; border: 1.5px solid #e4e4e0; color: #262826; }
    .pp-area.is-on { background: #f7f7f5; border-color: #262826; }
    .pp-area-name { display: flex; align-items: center; gap: 8px; }
    .pp-radio { width: 14px; height: 14px; border-radius: 50%; border: 4px solid #d6d6d1; background: #fff; flex: none; }
    .pp-area.is-on .pp-radio { border-color: #262826; }
    .pp-summary { background: #f7f7f5; border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; font-size: 15px; }
    .pp-summary > div { display: flex; justify-content: space-between; gap: 8px; }
    .pp-summary .pp-total { border-top: 1px dashed #cfcfca; padding-top: 8px; margin-top: 2px; font-size: 18px; font-weight: 700; }
    .pp-done { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; padding: 16px 0; font-size: 15px; }
    .pp-done-check { width: 52px; height: 52px; border-radius: 50%; background: #eaf7ef; color: #2f9e44; display: flex; align-items: center; justify-content: center; font-size: 26px; }
    .pp-btn-ghost { margin-top: 6px; border: 1.5px solid #262826; background: #fff; border-radius: 10px; height: 42px; padding: 0 16px; cursor: pointer; font-size: 15px; color: #262826; }

    /* description + policy */
    .pp-text { font-size: 15px; color: #3f423f; }
    .pp-features, .pp-policy { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; font-size: 15px; color: #3f423f; }
    .pp-features li, .pp-policy li { display: flex; gap: 10px; }
    .pp-tick { color: #2f9e44; flex: none; }
    .pp-policy { margin-top: 12px; gap: 10px; } .pp-policy li > span:first-child { flex: none; }
    .pp details > summary, details.pp-card > summary { cursor: pointer; }

    @media (max-width: 939px) { .pp-gallery { position: static; } }
    @media (max-width: 480px) { .pp { padding: 12px 14px 24px; } .pp-title { font-size: 24px; } .pp-price { font-size: 28px; } .pp-btn, .pp-btn-outline { font-size: 16px; } }
</style>
