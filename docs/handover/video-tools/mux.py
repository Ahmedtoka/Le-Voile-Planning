# -*- coding: utf-8 -*-
"""Mux the Playwright screen recording with the narration clips placed at their timeline offsets."""
import json, os, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
FF = r'C:\ffmpeg\ffmpeg-9.0.2-essentials_build\bin\ffmpeg.exe'
FFPROBE = r'C:\ffmpeg\ffmpeg-9.0.2-essentials_build\bin\ffprobe.exe'
out_path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, 'walkthrough.mp4')
AUDIO_DIR = os.environ.get('AUDIO_DIR', 'audio')
REC_DIR = os.environ.get('REC_DIR', 'record')

tl = json.load(open(os.path.join(HERE, REC_DIR, 'timeline.json'), encoding='utf-8'))
video = tl['video']
items = [e for e in tl['timeline'] if os.path.exists(os.path.join(HERE, AUDIO_DIR, e['key'] + '.mp3'))]

inputs = ['-i', video]
filters = []
labels = []
for i, e in enumerate(items, start=1):
    inputs += ['-i', os.path.join(HERE, AUDIO_DIR, e['key'] + '.mp3')]
    ms = int(round(e['t'] * 1000))
    filters.append(f'[{i}:a]aresample=48000,aformat=channel_layouts=stereo,adelay={ms}|{ms}[a{i}]')
    labels.append(f'[a{i}]')
filters.append(''.join(labels) + f'amix=inputs={len(labels)}:normalize=0:dropout_transition=0,apad[aout]')
fc = ';'.join(filters)

vdur = float(subprocess.run([FFPROBE, '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', video], capture_output=True, text=True).stdout.strip() or 0)
print('video duration', vdur, 'scenes', len(items))

cmd = [FF, '-y', *inputs, '-filter_complex', fc, '-map', '0:v', '-map', '[aout]',
       '-c:v', 'libx264', '-preset', 'medium', '-crf', '22', '-pix_fmt', 'yuv420p', '-r', '25',
       '-c:a', 'aac', '-b:a', '128k', '-shortest', '-movflags', '+faststart', out_path]
r = subprocess.run(cmd, capture_output=True, text=True)
print(r.stderr[-1500:])
print('exit', r.returncode, out_path, os.path.getsize(out_path) if os.path.exists(out_path) else None)
