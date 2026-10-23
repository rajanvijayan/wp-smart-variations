/**
 * SwatchCraft front end.
 */
import initGallery from './gallery';
import initLoop from './loop';
import initSwatches from './swatches';
import initTable from './table';

import './index.scss';

function start() {
	initSwatches();
	initLoop();
	initGallery();
	initTable();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
