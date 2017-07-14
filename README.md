# mjj-anon-gravatar

This is a proof of concept on how Gravatars could be anonymised. It's currently not quite finished but finished enough so if you treat it gently, it's possible to get an idea about how it works.

It would work much better if Gravatar worked slightly differently; [here's an idea on that](https://paper.dropbox.com/doc/Gravatar-with-paragraphs-k7IAOntjmDRZDHrYRYWgn). 

This doesn't work with Buddypress and I haven't finished the profile bit where users will be able to choose between their Gravatar and the default image. If a user doesn't have a Gravatar account, it will currently save the default generated avatar. This won't last, it's just because I'm not done yet. ☺️

The hash used to get the default avatar is `$user_id . site_url()` so it's unique to each user although as I typed this, I realised it'll change if you change from http to https. Not sure that really matters as this is, again, just a proof of concept.

The image from Gravatar is saved and that url is used as the img-src.
