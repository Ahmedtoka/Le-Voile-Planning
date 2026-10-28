# -*- coding: utf-8 -*-
"""LvPlanning cycle walkthrough — dry run (screenshots) or record (video + timeline).
Usage: python tour.py dry | python tour.py record
"""
import sys, os, re, json, time, datetime
from playwright.sync_api import sync_playwright

MODE = sys.argv[1] if len(sys.argv) > 1 else 'dry'
BASE = 'http://localhost/LvPlanning/public'
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, MODE)
os.makedirs(OUT, exist_ok=True)
VW, VH = 1600, 900

# narration durations (seconds) per scene key — filled by audio step; in dry mode ignored
DUR = {}
AUDIO_DIR = os.environ.get('AUDIO_DIR', 'audio')
if MODE == 'record' and os.path.exists(os.path.join(HERE, AUDIO_DIR, 'durations.json')):
    DUR = json.load(open(os.path.join(HERE, AUDIO_DIR, 'durations.json'), encoding='utf-8'))

timeline = []
T0 = None
state = {'po_id': None, 'po_no': None}


def now():
    return time.time() - T0


def mark(key, note=''):
    """narration for `key` starts now; returns the time we must not pass before next scene."""
    t = now()
    timeline.append({'key': key, 't': round(t, 3), 'note': note})
    print(f'[{t:7.2f}] {key} {note}')
    return t


def hold(key, t_start, extra=0.8):
    """wait until narration of `key` (started at t_start) has finished (+extra)."""
    if MODE != 'record':
        return
    d = DUR.get(key, 3.0)
    end = t_start + d + extra
    while now() < end:
        time.sleep(0.1)


def shot(page, name):
    page.screenshot(path=os.path.join(OUT, f'{name}.png'))


def settle(page, ms=900):
    page.wait_for_load_state('networkidle')
    page.wait_for_timeout(ms)


def login(page, user):
    page.goto(BASE + '/login')
    settle(page, 400)
    btn = page.locator(f'form[action$="/login/as/{user}"] button')
    if btn.count() == 0:
        # already logged in → logout first
        page.goto(BASE + '/')
        page.locator('form[action$="/logout"] button').first.click()
        page.wait_for_load_state('networkidle')
        page.goto(BASE + '/login')
        settle(page, 400)
        btn = page.locator(f'form[action$="/login/as/{user}"] button')
    btn.first.scroll_into_view_if_needed()
    btn.first.click()
    page.wait_for_load_state('networkidle')
    page.wait_for_timeout(600)


def logout(page):
    f = page.locator('form[action$="/logout"] button')
    if f.count():
        f.first.click()
        page.wait_for_load_state('networkidle')


def smooth_scroll(page, px, steps=8):
    for _ in range(steps):
        page.mouse.wheel(0, px / steps)
        page.wait_for_timeout(120)


def run(page):
    global T0
    T0 = time.time()
    today = datetime.date.today()

    # ── 0. intro: login screen ─────────────────────────────────────
    page.goto(BASE + '/login')
    settle(page)
    t = mark('s00_intro')
    shot(page, 's00_login')
    hold('s00_intro', t)

    # ── 1. planner: desk + new PO ──────────────────────────────────
    login(page, 'mostafa')
    page.goto(BASE + '/desk/planning'); settle(page)
    t = mark('s01_desk')
    shot(page, 's01_desk_planning')
    hold('s01_desk', t)

    page.goto(BASE + '/purchase-orders/create'); settle(page)
    t = mark('s02_po_form')
    shot(page, 's02_po_create_empty')
    page.fill('.mp-search', 'MDL-012')
    page.wait_for_selector('.mp-results button', timeout=5000)
    page.wait_for_timeout(700)
    page.locator('.mp-results button').first.dispatch_event('mousedown')
    page.wait_for_timeout(1500)   # fabrics-of fetch fills the line
    page.fill('#target_pieces', '1300')
    page.wait_for_timeout(1200)
    # pick the colour (white) on the first line
    csel = page.locator('select[name="lines[0][color_id]"]')
    val = csel.evaluate("s => { const o=[...s.options].find(o=>o.textContent.includes('أبيض')); return o ? o.value : s.options[1].value; }")
    csel.select_option(val)
    page.wait_for_timeout(600)
    shot(page, 's02_po_create_filled')
    hold('s02_po_form', t)

    t = mark('s03_po_submit')
    page.locator('form button.btn-plum:has-text("اطلب")').click()
    page.wait_for_load_state('networkidle'); page.wait_for_timeout(800)
    shot(page, 's03_po_index_after')
    # new PO id = highest edit link
    hrefs = page.locator('a[href*="/purchase-orders/"][href$="/edit"]').evaluate_all('els => els.map(e => e.getAttribute("href"))')
    ids = [int(m.group(1)) for h in hrefs for m in [re.search(r'/purchase-orders/(\d+)/edit', h)] if m]
    state['po_id'] = max(ids) if ids else None
    txt = page.locator('body').inner_text()
    m = re.findall(r'PO-\d{4}-\d{5}', txt)
    state['po_no'] = sorted(set(m))[-1] if m else None
    print('new PO', state)
    hold('s03_po_submit', t)

    # ── 2. purchasing: source it (partial) ─────────────────────────
    logout(page); login(page, 'ahmed')
    page.goto(BASE + '/purchasing'); settle(page)
    t = mark('s04_purchasing_queue')
    shot(page, 's04_purchasing_queue')
    hold('s04_purchasing_queue', t)

    page.goto(f'{BASE}/purchasing/{state["po_id"]}'); settle(page)
    t = mark('s05_source_form')
    shot(page, 's05_source_empty')
    sup = page.locator('#sup')
    try:
        sup.select_option(label='الإسكندرية للنسيج')
    except Exception:
        sup.select_option(index=1)
    page.wait_for_timeout(500)
    page.fill('#delivery_date', (today + datetime.timedelta(days=14)).isoformat())
    page.locator('#payment_method').select_option(index=1)
    q = page.locator('.s-qty').first
    q.fill('200')
    page.locator('.s-price').first.fill('190')
    page.wait_for_timeout(800)
    shot(page, 's05_source_filled')
    hold('s05_source_form', t)

    t = mark('s06_source_submit')
    page.locator('form button.btn-plum:has-text("سكّن")').click()
    page.wait_for_load_state('networkidle'); page.wait_for_timeout(800)
    shot(page, 's06_after_source')
    hold('s06_source_submit', t)

    # ── 3. finance ack ─────────────────────────────────────────────
    logout(page); login(page, 'dina')
    page.goto(BASE + '/finance/payables'); settle(page)
    t = mark('s07_payables')
    shot(page, 's07_payables')
    hold('s07_payables', t)
    page.goto(f'{BASE}/finance/orders/{state["po_id"]}'); settle(page)
    t = mark('s08_finance_ack')
    shot(page, 's08_finance_form')
    page.locator('form button.btn-plum:has-text("علمت")').click()
    page.wait_for_load_state('networkidle'); page.wait_for_timeout(700)
    shot(page, 's08_finance_after')
    hold('s08_finance_ack', t)

    # ── 4. store: stock addition (live net/gross) ──────────────────
    logout(page); login(page, 'hazem')
    page.goto(BASE + '/desk/store'); settle(page)
    t = mark('s09_store_desk')
    shot(page, 's09_store_desk')
    hold('s09_store_desk', t)

    page.goto(f'{BASE}/stock-additions/create?purchase_order_id={state["po_id"]}'); settle(page)
    t = mark('s10_sa_form')
    shot(page, 's10_sa_empty')
    try:
        if page.locator('#paper_serial').count(): page.fill('#paper_serial', '1047')
        if page.locator('#supplier_doc_no').count(): page.fill('#supplier_doc_no', 'ALX-7781')
        row = page.locator('#lines tr').first
        row.locator('.q-rolls').fill('10')
        row.locator('.q-form').select_option('open')
        page.wait_for_timeout(300)
        row.locator('.q-bag').fill('30')
        row.locator('.q-tube').fill('20')
        row.locator('.q-gross').fill('205')
        page.wait_for_timeout(900)
        eta = row.locator('input[name="lines[0][remainder_eta]"]')
        if eta.count() and eta.first.is_visible():
            eta.first.fill((today + datetime.timedelta(days=20)).isoformat())
            page.wait_for_timeout(300)
        smooth_scroll(page, 500, 5)
        page.wait_for_timeout(500)
        shot(page, 's10_sa_filled')
    except Exception as e:
        print('SA fill failed:', e)
    hold('s10_sa_form', t)

    t = mark('s11_sa_submit')
    try:
        page.locator('form button.btn-plum[type=submit], form .form-actions button.btn-plum').first.click()
        page.wait_for_load_state('networkidle'); page.wait_for_timeout(900)
        shot(page, 's11_sa_after')
        print('SA after url', page.url)
    except Exception as e:
        print('SA submit failed:', e)
    hold('s11_sa_submit', t)

    # from here: the completed meeting-37 test cycle (PO-2026-00018) so every number is final
    page.goto(f'{BASE}/stock-additions/18/edit'); settle(page)
    t = mark('s12_sa18')
    shot(page, 's12_sa18_top')
    smooth_scroll(page, 700, 6); page.wait_for_timeout(400)
    shot(page, 's12_sa18_bottom')
    hold('s12_sa18', t)

    # ── 5. quality: inspection + lab in one sheet ──────────────────
    logout(page); login(page, 'ibrahim')
    page.goto(BASE + '/desk/quality'); settle(page)
    t = mark('s13_quality_desk')
    shot(page, 's13_quality_desk')
    hold('s13_quality_desk', t)
    page.goto(f'{BASE}/inspections/20/edit'); settle(page)
    t = mark('s14_inspection')
    shot(page, 's14_insp_top')
    smooth_scroll(page, 700, 6); page.wait_for_timeout(400)
    shot(page, 's14_insp_mid')
    hold('s14_inspection', t)
    t = mark('s15_lab')
    smooth_scroll(page, 900, 6); page.wait_for_timeout(400)
    shot(page, 's15_insp_lab')
    hold('s15_lab', t)

    # ── 6. store: goods receipt (release) + consignment ────────────
    logout(page); login(page, 'hazem')
    page.goto(f'{BASE}/goods-receipts/14/edit'); settle(page)
    t = mark('s16_receipt')
    shot(page, 's16_gr_top')
    smooth_scroll(page, 700, 6); page.wait_for_timeout(400)
    shot(page, 's16_gr_bottom')
    hold('s16_receipt', t)
    page.goto(f'{BASE}/consignments/25'); settle(page)
    t = mark('s17_consignment')
    shot(page, 's17_consignment')
    hold('s17_consignment', t)
    page.goto(f'{BASE}/stock/onhand'); settle(page)
    t = mark('s18_onhand')
    shot(page, 's18_onhand')
    hold('s18_onhand', t)

    # ── 7. planner: preparation + marker request ───────────────────
    logout(page); login(page, 'mostafa')
    page.goto(f'{BASE}/preparation-requests'); settle(page)
    t = mark('s19_prep_index')
    shot(page, 's19_prep_index')
    hold('s19_prep_index', t)
    page.goto(f'{BASE}/preparation-requests/create'); settle(page)
    t = mark('s20_prep_form')
    shot(page, 's20_prep_form')
    hold('s20_prep_form', t)
    page.goto(f'{BASE}/marker-requests'); settle(page)
    t = mark('s21_marker_requests')
    shot(page, 's21_marker_requests')
    hold('s21_marker_requests', t)

    # ── 8. patternist: marker ──────────────────────────────────────
    logout(page); login(page, 'yasmin')
    page.goto(f'{BASE}/markers'); settle(page)
    t = mark('s22_markers')
    shot(page, 's22_markers')
    hold('s22_markers', t)
    page.goto(f'{BASE}/markers/19/edit'); settle(page)
    t = mark('s23_marker19')
    shot(page, 's23_marker19')
    hold('s23_marker19', t)

    # ── 9. planner: work order ─────────────────────────────────────
    logout(page); login(page, 'mostafa')
    page.goto(f'{BASE}/work-orders'); settle(page)
    t = mark('s24_wo_index')
    shot(page, 's24_wo_index')
    hold('s24_wo_index', t)
    page.goto(f'{BASE}/work-orders/17'); settle(page)
    t = mark('s25_wo17_top')
    shot(page, 's25_wo17_top')
    hold('s25_wo17_top', t)
    t = mark('s26_wo17_calc')
    smooth_scroll(page, 800, 6); page.wait_for_timeout(400)
    shot(page, 's26_wo17_calc')
    hold('s26_wo17_calc', t)
    t = mark('s27_wo17_more')
    smooth_scroll(page, 800, 6); page.wait_for_timeout(400)
    shot(page, 's27_wo17_more')
    hold('s27_wo17_more', t)

    # ── 10. store: material issue ──────────────────────────────────
    logout(page); login(page, 'hazem')
    page.goto(f'{BASE}/material-issues/11/edit'); settle(page)
    t = mark('s28_issue')
    shot(page, 's28_issue_top')
    smooth_scroll(page, 600, 5); page.wait_for_timeout(400)
    shot(page, 's28_issue_bottom')
    hold('s28_issue', t)

    # ── 11. factory: portal, cut declaration, production receipt ───
    logout(page); login(page, 'mahmoud')
    settle(page)
    t = mark('s29_portal')
    shot(page, 's29_portal')
    hold('s29_portal', t)
    page.goto(f'{BASE}/cut-declarations/8/edit'); settle(page)
    t = mark('s30_cut')
    shot(page, 's30_cut')
    hold('s30_cut', t)
    page.goto(f'{BASE}/production-receipts/9/edit'); settle(page)
    t = mark('s31_prod_receipt')
    shot(page, 's31_prod_receipt')
    hold('s31_prod_receipt', t)

    # ── 12. admin: dashboard + rules + outro ───────────────────────
    logout(page); login(page, 'admin')
    page.goto(BASE + '/'); settle(page)
    t = mark('s32_dashboard')
    shot(page, 's32_dashboard')
    hold('s32_dashboard', t)
    page.goto(BASE + '/settings/rules'); settle(page)
    t = mark('s33_rules')
    shot(page, 's33_rules')
    hold('s33_rules', t)
    page.goto(BASE + '/tour.html'); settle(page)
    t = mark('s34_outro')
    shot(page, 's34_outro')
    hold('s34_outro', t, extra=1.5)


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    ctx_kwargs = dict(viewport={'width': VW, 'height': VH}, locale='ar-EG', device_scale_factor=1)
    if MODE == 'record':
        ctx_kwargs['record_video_dir'] = os.path.join(OUT, 'video')
        ctx_kwargs['record_video_size'] = {'width': VW, 'height': VH}
    ctx = browser.new_context(**ctx_kwargs)
    page = ctx.new_page()
    page.on('console', lambda m: print('CONSOLE', m.type, m.text) if m.type == 'error' else None)
    try:
        run(page)
    finally:
        vpath = None
        if MODE == 'record':
            vpath = str(page.video.path())
        ctx.close()
        browser.close()
        json.dump({'timeline': timeline, 'video': vpath, 'state': state}, open(os.path.join(OUT, 'timeline.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        print('video:', vpath)
