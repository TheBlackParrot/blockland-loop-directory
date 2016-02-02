$("body").on("click", ".play", function(){
	playLoop($(this).attr("file"));

	// removeClass wasn't doing crap
	$(".stop").each(function(){
		$(this).attr("class", "fa fa-fw play fa-play");
	});
	$(this).attr("class", "fa fa-fw stop fa-stop");
});

$("body").on("click", ".stop", function(){
	audioSource.stop();
	$(this).attr("class", "fa fa-fw play fa-play");
});