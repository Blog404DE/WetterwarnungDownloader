#!/bin/bash

# Zugangsdaten zum DWD Grundversorgungs-Server
FTPUSER="**********"
FTPPASSWD="********"

echo Lade Liste mit WarnCellID:
wget ftp://$FTPUSER:$FTPPASSWD@ftp-outgoing2.dwd.de/gds/help/legend_warnings_CAP_WarnCellsID.csv

echo Starte Download der restlichen Entwickler-Dokumentation und FAQ vom DWD:
wget ftp://$FTPUSER:$FTPPASSWD@ftp-outgoing2.dwd.de/gds/help/legend_warnings.pdf
wget ftp://$FTPUSER:$FTPPASSWD@ftp-outgoing2.dwd.de/gds/help/legend_warnings_CAP.pdf
wget ftp://$FTPUSER:$FTPPASSWD@ftp-outgoing2.dwd.de/gds/help/legend_warnings_CAP_FAQ.pdf

exit 0