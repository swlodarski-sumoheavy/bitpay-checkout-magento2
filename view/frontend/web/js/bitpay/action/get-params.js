define([], function () {
    'use strict';

    return function () {
        var vars = {};

        window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m,key,value) {
            vars[key] = value;
        });

        return vars;
    };
});
