{{--
  رأس مشترك لصفحات الخطأ (403/404/419/500/503).
  مستقل عن الـlayout ومن غير داتابيز — عشان يشتغل حتى لو السيستم واقع.
  بياخد: $title (عنوان التبويب من غير «— Le Voile»).
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $title }} — Le Voile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
 :root{
   --bg:#F8F4F1; --card:#fff; --ink:#2B2028; --muted:#6B606A; --line:#EAE2E7;
   --plum:#9D197E; --plum-d:#7A1362; --plum-t:#F7E8F3; --plum-r:#ECD1E5;
   --r:14px;
   --sh:0 12px 32px -10px rgba(59,9,47,.22), 0 2px 6px rgba(59,9,47,.06);
   --ff:'IBM Plex Sans Arabic', system-ui, 'Segoe UI', Tahoma, sans-serif;
 }
 *{box-sizing:border-box}
 html,body{margin:0}
 body{font-family:var(--ff);background:var(--bg);color:var(--ink);min-height:100vh;
   display:flex;align-items:center;justify-content:center;padding:32px 16px;line-height:1.7}
 .card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--sh);
   width:100%;max-width:520px;padding:40px 36px 32px;text-align:center}
 .mark{display:block;width:132px;height:104px;margin:0 auto 18px}
 .mark .num{font-family:var(--ff);font-weight:700;font-size:40px;fill:var(--plum);letter-spacing:1px}
 h1{font-size:1.3rem;font-weight:700;margin:0 0 10px;line-height:1.5}
 p{color:var(--muted);font-size:.95rem;margin:0 0 12px}
 p.lead{color:var(--ink)}
 .path{display:inline-block;direction:ltr;unicode-bidi:isolate;background:var(--plum-t);color:var(--plum-d);
   border:1px solid var(--plum-r);border-radius:8px;padding:2px 10px;font-size:.85rem;
   font-family:ui-monospace,Consolas,monospace;word-break:break-all;max-width:100%}
 .actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:24px}
 .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:44px;padding:10px 22px;
   border-radius:10px;font-family:var(--ff);font-size:.95rem;font-weight:600;cursor:pointer;text-decoration:none;
   border:1.5px solid transparent;transition:background .15s,color .15s,border-color .15s}
 .btn-plum{background:var(--plum);color:#fff;border-color:var(--plum)}
 .btn-plum:hover,.btn-plum:focus{background:var(--plum-d);border-color:var(--plum-d);color:#fff}
 .btn-outline{background:#fff;color:var(--plum);border-color:var(--plum)}
 .btn-outline:hover,.btn-outline:focus{background:var(--plum-t);color:var(--plum-d)}
 .btn:focus-visible{outline:3px solid var(--plum-r);outline-offset:2px}
 .btn svg{width:18px;height:18px;flex:none}
 .muted{color:var(--muted);font-size:.82rem;margin:18px 0 0}
 .muted code{direction:ltr;unicode-bidi:isolate;display:inline-block;background:#F1ECEF;border-radius:6px;
   padding:1px 8px;font-family:ui-monospace,Consolas,monospace;font-size:.8rem;color:var(--ink)}
 .stamp{display:inline-block;direction:ltr;unicode-bidi:isolate;font-family:ui-monospace,Consolas,monospace;
   font-weight:600;color:var(--ink);background:#F1ECEF;border-radius:6px;padding:2px 10px}
 .foot{margin-top:22px;padding-top:14px;border-top:1px solid var(--line);color:var(--muted);font-size:.8rem}
 .foot a{color:var(--plum);font-weight:600;text-decoration:none}
 .foot a:hover,.foot a:focus{text-decoration:underline}
 @media (max-width:480px){
   body{padding:20px 16px;align-items:flex-start}
   .card{padding:30px 20px 24px}
   .actions{flex-direction:column}
   .btn{width:100%}
   h1{font-size:1.15rem}
 }
 @media (prefers-reduced-motion:no-preference){
   .card{animation:in .35s ease-out}
   @keyframes in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
 }
</style>
