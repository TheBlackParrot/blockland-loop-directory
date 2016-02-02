var audioContext = new AudioContext();
var audioSource = audioContext.createBufferSource();

// i love and despise web audio API

var playing = 0;

function playLoop(filename) {
	console.log("playing " + filename);

	if(playing) {
		audioSource.stop();
	}
	playing = 1;
	audioSource = audioContext.createBufferSource();
	audioSource.connect(audioContext.destination);

	var request = new XMLHttpRequest();
	request.open('GET', 'loops/' + filename, true);
	request.responseType = 'arraybuffer';

	request.onload = function(){
		console.log("loaded");

		audioContext.decodeAudioData(request.response, function(response){
			audioSource.buffer = response;
			audioSource.start();
			audioSource.loop = true;
		}, function(){
			console.error('Failed to play loop.');
		});
	}

	request.send();
}