'use strict';

import '../sass/admin.scss';
import Confirm from './components/Confirm.js';
import Progress from './components/Progress.js';
import Searches from './components/Searches.js';

(function() {
    window.NAILS.ADMIN
        .registerPlugin('nails/module-find-replace', 'Confirm', (controller) => new Confirm(controller))
        .registerPlugin('nails/module-find-replace', 'Progress', () => new Progress())
        .registerPlugin('nails/module-find-replace', 'Searches', () => new Searches());
})();
