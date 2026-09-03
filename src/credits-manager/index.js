import './editor.scss';
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import CreditsManager from './CreditsManager';

domReady(() => {
	const container = document.getElementById('theatrum-credits-manager-root');
	if (!container) {
		return;
	}
	createRoot(container).render(<CreditsManager />);
});
