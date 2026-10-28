# -*- coding: utf-8 -*-
"""Generate Egyptian-Arabic narration clips with edge-tts and write durations.json"""
import asyncio, json, os, subprocess, sys
import edge_tts

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'audio')
os.makedirs(OUT, exist_ok=True)
FFPROBE = r'C:\ffmpeg\ffmpeg-9.0.2-essentials_build\bin\ffprobe.exe'
VOICE = 'ar-EG-ShakirNeural'
RATE = '+4%'

texts = json.load(open(os.path.join(HERE, 'narration.json'), encoding='utf-8'))
only = sys.argv[1:]  # optional subset of keys


async def gen(key, text):
    path = os.path.join(OUT, f'{key}.mp3')
    for attempt in range(4):
        try:
            c = edge_tts.Communicate(text, VOICE, rate=RATE)
            await c.save(path)
            return
        except Exception as e:
            print('retry', key, attempt, e)
            await asyncio.sleep(2)
    raise RuntimeError(key)


async def main():
    for k, t in texts.items():
        if only and k not in only:
            continue
        await gen(k, t)
        print('ok', k)

asyncio.run(main())

dur = {}
for k in texts:
    p = os.path.join(OUT, f'{k}.mp3')
    if os.path.exists(p):
        d = subprocess.run([FFPROBE, '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', p], capture_output=True, text=True).stdout.strip()
        dur[k] = float(d)
json.dump(dur, open(os.path.join(OUT, 'durations.json'), 'w'), indent=1)
print('total narration seconds:', round(sum(dur.values()), 1))
