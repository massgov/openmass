/**
 * @file
 * Renders map on .js-google-map div for map row page.
 *
 * Loads google maps results (loads once)
 */
(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.massMap = {
    attach: function (context, settings) {
      var mapId = '.js-google-map';
      var locations = drupalSettings.locations;

      // Using once() to apply the myCustomBehaviour effect when you want to do just run one function.
      var $elements = $(once('js-google-map', '.js-google-map', context));
      $elements.each(function () {
        $(this).addClass('mass-map-processed');
        // Set the height so the map is visible.
        $(this).height('500px');
        // Create a map with its center at the center of MA
        var mapProp = {
          center: new google.maps.LatLng(42.4072107, -71.3824374),
          zoom: 8,
          scrollwheel: false
        };
        var map = new google.maps.Map($(this)[0], mapProp);
        // Keep track of the bounds so we can adjust based on markers.
        var bounds = new google.maps.LatLngBounds();
        // Info windows to label map points
        var infowindow = new google.maps.InfoWindow();

        // Go over list of locations,.
        for (var key in locations.googleMap.markers) {
          if (Object.prototype.hasOwnProperty.call(locations.googleMap.markers, key)) {
            var infoWindowData = infoWindow(locations.googleMap.markers[key].infoWindow);
            // Set the marker of the location.
            var marker = new google.maps.Marker({
              position: new google.maps.LatLng(
                locations.googleMap.markers[key].position.lat,
                locations.googleMap.markers[key].position.lng),
              _windowInfo: infoWindowData,
              _nid: key
            });
            marker.setMap(map);

            // extend the bounds to include each marker's position
            bounds.extend(marker.position);

            // Add information to the info windo of that marker.
            google.maps.event.addListener(marker, 'click', (function (marker, infoWindowData) {
              return function () {
                infowindow.setContent(infoWindowData);
                infowindow.open(map, marker);
              };
            })(marker, infoWindowData));
          }
        }
        $('.ma__content-link').attr('href', '/map/' + drupalSettings.nodeId);

        // now fit the map to the newly inclusive bounds
        map.fitBounds(bounds);
      });
    }
  };
})(jQuery, Drupal);

// For LeafletMap infoWindow content, 'infoWindow' is not used.
// See L.1103 of modules/custom/mayflower/src/Prepare/Molecules.php.
var infoWindow = function (infoWindow) {
  'use strict';
  var info = '';
  // infoWindow data.
  if (infoWindow.name) {info += '<h3 class="ma__info-window__name">' + infoWindow.name + '</h3>';}
  if (infoWindow.address) {info += '<p class="ma__info-window__address">' + infoWindow.address + '</p>';}
  if (infoWindow.phone) {info += '<div class="ma__info-window__phone"><span class="ma__info-window__label">Phone:&nbsp;</span><a class="ma__info-window__phone" href="tel:' + infoWindow.phone + '">' + infoWindow.phone + '</a></div>';}
  if (infoWindow.email) {info += '<div class="ma__info-window__email"><span class="ma__info-window__label">Email:&nbsp;</span><a class="ma__info-window__email" href="mailto:' + infoWindow.email + '">' + infoWindow.email + '</a></div>';}
  if (infoWindow.directions) {info += '<div class="ma__info-window__directions"><span class="ma__decorative-link"><a href="' + infoWindow.directions + '">Directions <svg aria-hidden="true" id="SvgjsSvg1000" xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs" width="16" height="18" viewBox="0 0 16 18"><defs id="SvgjsDefs1001"></defs><path id="SvgjsPath1007" d="M983.721 1887.28L983.721 1887.28L986.423 1890L986.423 1890L986.423 1890L983.721 1892.72L983.721 1892.72L978.318 1898.17L975.617 1895.45L979.115 1891.92L971.443 1891.92L971.443 1888.0700000000002L979.103 1888.0700000000002L975.617 1884.5500000000002L978.318 1881.8300000000002Z " transform="matrix(1,0,0,1,-971,-1881)"></path></svg></a></span></span></div>';}
  info = '<section class="ma__info-window">' + info + '</section>';
  return info;
};
