# PHP/PfSense CaptivePortal Radius Authentication Overwrite Hack.
Converts the pfsense captive portal authentication mecanism into a radius mobile network authentication border control gateway.

Allows mobile incoming network traffic authentication based on pfsense radius authentication system.

After radius authentication `sedin` and `sedout` shell scripts set necessary network firewall rules to pfsense using pfctl cli utility.

The `index.php` must be placed in the captive portal webroot directory since this is the main endpoint whenever pfsense receives network traffic.

Please take a look to this incredible open source captive portal and network security solution at [pfsense.org](https://www.pfsense.org/)
