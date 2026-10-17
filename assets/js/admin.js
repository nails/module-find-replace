'use strict';

import '../sass/admin.scss';
import Confirm from './components/Confirm.js';
import Progress from './components/Progress.js';

(function() {
    window.NAILS.ADMIN
        .registerPlugin('nails/module-find-replace', 'Confirm', (controller) => new Confirm(controller))
        .registerPlugin('nails/module-find-replace', 'Progress', () => new Progress());
})();
