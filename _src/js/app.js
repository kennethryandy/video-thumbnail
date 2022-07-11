
const testVideos  =  Array.from(document.getElementsByClassName('test-video'));
const spinner     =  document.getElementById('spinner');

async function fetchThumbnails()
{
	await Promise.all(testVideos.map( async e => {
		spinner.style.display = 'flex';
		const baseUrl = 'http://localhost/portal-core/video-thumbs?'
		const req = e.innerHTML;
		const res = await fetch(baseUrl + req);
		const data = await res.json();

		if( data?.success === 1 )
		{
			const img = document.createElement('img');
			img.src = data.spacesimgurl;
			e.appendChild(img);
		}else {
			const pre = document.createElement('pre');
			const json = document.createTextNode(JSON.stringify(data));
			pre.appendChild(json);
			e.appendChild(pre);
		}
	}))

	spinner.style.display = 'none';
}

fetchThumbnails();