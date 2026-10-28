# أدوات إعادة تسجيل فيديو الدورة

- `narration.json` — نص التعليق لكل مشهد (المفتاح = اسم المشهد في `tour.py`).
- `gen_audio.py` — يولّد `audio/*.mp3` بـ edge-tts (`ar-EG-ShakirNeural`) ويكتب `audio/durations.json`.
- `tour.py` — Playwright (Python): `python tour.py dry` سكرينشوتات بس للمراجعة، `python tour.py record` تسجيل فيديو موقّت على مدة كل مقطع صوتي.
- `mux.py` — يدمج التعليق على التسجيل بـ ffmpeg حسب `record/timeline.json`.

المتطلبات: `pip install playwright edge-tts` + `python -m playwright install chromium`، وffmpeg (`C:\ffmpeg\ffmpeg-9.0.2-essentials_build\bin`)، والمشروع شغال على `http://localhost/LvPlanning/public` بالدخول التجريبي مفعّل (`APP_ENV=local`).
الترتيب: `gen_audio.py` ⇐ `tour.py record` ⇐ `mux.py <output.mp4>`. السكريبت بيعمل طلب شراء وتسكينة وإذن إضافة جديدة في الداتابيز في كل تشغيل.

## نسخة بصوت المالك (ElevenLabs)

`el_gen.py` بيقرا `ELEVENLABS_API_KEY` و`ELEVENLABS_VOICE_ID` من `C:\xampp\htdocs\AzzaB\jarvis\.env` (أو من متغيرات البيئة) وقت التشغيل ومش بيطبع المفتاح، ويولّد نفس الـ35 مقطع في `audio_el/` بموديل `eleven_multilingual_v2` (ويرجع لموديل الـenv لو اترفض). التكلفة ≈ 9,300 حرف.
بعدها (من نفس المجلد اللي فيه السكريبتات — التسجيل الجديد بيكتب فوق مجلد `record/`، والنسخة القديمة بصوت TTS خلاص اتعملت):

```bash
python el_gen.py
```
```bash
set AUDIO_DIR=audio_el&& python tour.py record
```
```bash
set AUDIO_DIR=audio_el&& python mux.py "C:\xampp\htdocs\LvPlanning\docs\handover\2026-10-24-lvplanning-cycle-walkthrough-owner-voice.mp4"
```
> التسجيل بيتعاد لأن مدة كل مقطع بتختلف عن مقاطع TTS، والفيديو بيقف على كل شاشة لحد ما مقطعه يخلص. إعادة التسجيل بتعمل طلب شراء/تسكينة/إذن إضافة جديدة في الداتابيز المحلية.
