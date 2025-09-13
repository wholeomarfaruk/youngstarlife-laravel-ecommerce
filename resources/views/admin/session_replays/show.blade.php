@extends('layouts.admin')

@section('content')
<div class="container mt-4">
    <h2>Replay Session #{{ $record->id }}</h2>
    <p><strong>Page:</strong> {{ $record->page_url }}</p>

    <!-- Replay Box -->
    <div id="replay-box" style="position:relative;width:100%;height:600px;border:1px solid #ccc;overflow:hidden;">
        <iframe src="{{ $record->page_url }}" style="width:100%;height:100%;border:0;"></iframe>
        <div id="cursor" style="position:absolute;top:0;left:0;width:20px;height:20px;border-radius:50%;background:red;pointer-events:none;"></div>
    </div>

    <!-- Controls -->
    <div class="mt-3 d-flex align-items-center gap-3">
        <button id="playPauseBtn" class="btn btn-primary">Play</button>
        <span>Time: <span id="currentTime">0</span> / <span id="totalTime">0</span> ms</span>
        <input type="range" id="progressBar" min="0" max="0" value="0" style="flex:1;">
    </div>
</div>

<script>
const events = @json($record->events);
const cursor = document.getElementById('cursor');
const iframe = document.querySelector('#replay-box iframe');
let iframeWindow = iframe.contentWindow;

let paused = true;
let currentIndex = 0;
let timer = null;
let startTime = events[0]?.time || 0;

const currentTimeSpan = document.getElementById('currentTime');
const totalTimeSpan = document.getElementById('totalTime');
const progressBar = document.getElementById('progressBar');

if(events.length){
    totalTimeSpan.innerText = events[events.length-1].time;
    progressBar.max = events[events.length-1].time;
}

function playReplay(){
    if(currentIndex >= events.length) return;
    let event = events[currentIndex];
    let delay = currentIndex === 0 ? event.time : event.time - events[currentIndex-1].time;

    timer = setTimeout(()=>{
        handleEvent(event);
        currentTimeSpan.innerText = event.time;
        progressBar.value = event.time;
        currentIndex++;
        if(!paused) playReplay();
    }, delay);
}

function handleEvent(event){
    if(event.type==="move"){
        cursor.style.transform = `translate(${event.data.x}px, ${event.data.y}px)`;
    }
    if(event.type==="click"){
        let dot = document.createElement("div");
        dot.style.position="absolute";
        dot.style.left=event.data.x+"px";
        dot.style.top=event.data.y+"px";
        dot.style.width="10px";
        dot.style.height="10px";
        dot.style.background="blue";
        dot.style.borderRadius="50%";
        dot.style.pointerEvents="none";
        document.getElementById('replay-box').appendChild(dot);
        setTimeout(()=>dot.remove(),800);
    }
    if(event.type==="scroll"){
        iframeWindow.scrollTo(0,event.data.y);
    }
    if(event.type==="input" || event.type==="change"){
        let el = iframeWindow.document.querySelector(event.data.selector);
        if(el){
            el.value = event.data.value;
            el.dispatchEvent(new Event('input',{bubbles:true}));
        }
    }
    if(event.type==="dom"){
        let el = iframeWindow.document.querySelector(event.data.selector);
        if(el) el.innerHTML = event.data.html;
    }
}

document.getElementById('playPauseBtn').addEventListener('click', ()=>{
    if(paused){
        paused=false;
        playReplay();
        document.getElementById('playPauseBtn').innerText='Pause';
    } else {
        paused=true;
        clearTimeout(timer);
        document.getElementById('playPauseBtn').innerText='Play';
    }
});

progressBar.addEventListener('input', ()=>{
    // Optional: allow jump to time
});
</script>
@endsection
