# -*- coding: utf-8 -*-
"""Generate the 35 narration clips with the owner's ElevenLabs voice clone.
Reads ELEVENLABS_API_KEY / ELEVENLABS_VOICE_ID from the jarvis .env (or from the environment) at runtime;
the key is never printed. Output: audio_el/<key>.mp3 + audio_el/durations.json (same layout as audio/).
Usage: python el_gen.py [key ...]   (optional subset of scene keys to (re)generate)
"""
import json, os, re, subprocess, sys, time, urllib.request, urllib.error

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'audio_el')
os.makedirs(OUT, exist_ok=True)
ENV = r'C:\xampp\htdocs\AzzaB\jarvis\.env'
FFPROBE = r'C:\ffmpeg\ffmpeg-9.0.2-essentials_build\bin\ffprobe.exe'
MODEL = 'eleven_multilingual_v2'      # best Arabic quality; falls back to the env model if rejected


def load_env(path):
    out = {}
    if os.path.exists(path):
        for line in open(path, encoding='utf-8', errors='ignore'):
            m = re.match(r'^\s*(ELEVENLABS_[A-Z_]+)\s*=\s*(.*?)\s*$', line)
            if m:
                out[m.group(1)] = m.group(2).strip('"\'')
    for k in ('ELEVENLABS_API_KEY', 'ELEVENLABS_VOICE_ID', 'ELEVENLABS_MODEL'):
        if os.environ.get(k):
            out[k] = os.environ[k]
    return out


env = load_env(ENV)
KEY, VOICE = env.get('ELEVENLABS_API_KEY'), env.get('ELEVENLABS_VOICE_ID')
if not KEY or not VOICE:
    sys.exit('ELEVENLABS_API_KEY / ELEVENLABS_VOICE_ID not found')
FALLBACK_MODEL = env.get('ELEVENLABS_MODEL') or 'eleven_flash_v2_5'

texts = json.load(open(os.path.join(HERE, 'narration.json'), encoding='utf-8'))
only = sys.argv[1:]


def tts(text, model):
    body = json.dumps({'text': text, 'model_id': model,
                       'voice_settings': {'stability': 0.5, 'similarity_boost': 0.8, 'style': 0.2, 'use_speaker_boost': True}}).encode('utf-8')
    req = urllib.request.Request(f'https://api.elevenlabs.io/v1/text-to-speech/{VOICE}?output_format=mp3_44100_128', data=body,
                                 headers={'xi-api-key': KEY, 'Content-Type': 'application/json', 'Accept': 'audio/mpeg'})
    return urllib.request.urlopen(req, timeout=180).read()


failed = []
for k, t in texts.items():
    if only and k not in only:
        continue
    path = os.path.join(OUT, f'{k}.mp3')
    ok = False
    for model in (MODEL, FALLBACK_MODEL):
        for attempt in range(3):
            try:
                data = tts(t, model)
                open(path, 'wb').write(data)
                print('ok', k, model, len(data), 'bytes')
                ok = True
                break
            except urllib.error.HTTPError as e:
                msg = e.read()[:300].decode('utf-8', 'ignore')
                print('http', e.code, k, model, msg)
                if e.code in (400, 401, 402, 403, 422):
                    break            # not retryable with this model
                time.sleep(3)
            except Exception as e:
                print('err', k, model, e)
                time.sleep(3)
        if ok:
            break
    if not ok:
        failed.append(k)

dur = {}
for k in texts:
    p = os.path.join(OUT, f'{k}.mp3')
    if os.path.exists(p):
        d = subprocess.run([FFPROBE, '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', p], capture_output=True, text=True).stdout.strip()
        dur[k] = float(d or 0)
json.dump(dur, open(os.path.join(OUT, 'durations.json'), 'w'), indent=1)
print('total seconds:', round(sum(dur.values()), 1), '| failed:', failed)
