// https://github.com/stefanocudini/leaflet-loader/blob/master/leaflet-loader.js
(function () {
    L.Control.Loader = L.Control.extend({
        onAdd: function (map) {
            this._map = map;
            this._container = L.DomUtil.create("div", "leaflet-control-loader");
            this.hide();
            return this._container;
        },
        addTo: function (map) {
            this._container = this.onAdd(map);
            map.getContainer().appendChild(this._container);
            return this;
        },
        show: function () {
            this._container.style.display = "block";
            return this;
        },
        hide: function () {
            this._container.style.display = "none";
            return this;
        },
    });

    L.Map.addInitHook(function () {
        if (this.options.loaderControl) {
            this.loaderControl = L.control.loader(this.options.loaderControl);
            this.addControl(this.loaderControl);
        }
    });

    L.control.loader = function (options) {
        return new L.Control.Loader(options);
    };

    // Override L.Icon.Default — vi serverar inte Leaflets default PNG-
    // markers (public/vendor/leaflet/images/ saknas medvetet — vi
    // använder CSS-styled divIcons överallt). Utan detta får vi 404
    // på marker-icon-2x.png m.fl. så fort något plugin (markercluster,
    // locatecontrol) eller annan kod skapar ett default-marker-objekt.
    var DefaultDivIcon = L.DivIcon.extend({
        options: {
            className: "Leaflet-default-marker",
            iconSize: [14, 14],
            iconAnchor: [7, 7],
            popupAnchor: [0, -7],
            html: '<span class="Leaflet-default-marker__dot"></span>',
        },
    });
    L.Icon.Default = DefaultDivIcon;
    L.Marker.prototype.options.icon = new DefaultDivIcon();
}).call(this);
