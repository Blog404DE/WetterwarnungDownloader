#!/bin/bash

echo "Lade Dbase-Datenbank mit WarnCellID und Bundesland"
echo "-> cap_community_shape_dwd_zip"
wget -q -O cap_community_shape_dwd_zip.zip "https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_community_shape_dwd_zip.zip?__blob=publicationFile&v=2"
if [ -x "$(command -v unzip)" ]; then
    echo -e "-> Versuche Dbase-Datenbank mit WarnCellID aus ZIP Datei zu entpacken ... \c"
    if unzip -p cap_community_shape_dwd_zip.zip Export/DWD-COMMUNITY.dbf > ./DWD-COMMUNITY.dbf; then
        rm cap_community_shape_dwd_zip.zip
        echo "Erfolgreich entpackt"
    else
        echo -e "\n** Datei cap_community_shape_dwd_zip.zip konnte nach dem entpacken nicht auautomatisch gelöscht werden (Datei kann per Hand gelöscht werden)" >&2
    fi
else
    echo "** Datei cap_community_shape_dwd_zip.zip konnte nicht automatisch entpackt werden (Datei bei Bedarf bitte per Hand entpacken)" >&2
fi

echo Starte Download der restlichen Entwickler-Dokumentation und FAQ vom DWD:
echo -e "-> cap_dwd_profile_de_pdf.pdf ... \c"
if wget -q -O cap_dwd_profile_de_pdf.pdf "https://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile"; then
    echo "Erfolgreich heruntergeladen"
else
    echo -e "\n** Fehler beim herunterladen der Datei cap_dwd_profile_de_pdf.pdf" >&2
fi

echo -e "-> cap_faq_de_pdf.pdf ... \c"
if wget -q -O cap_faq_de_pdf.pdf "https://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_faq_de_pdf.pdf?__blob=publicationFile"; then
    echo "Erfolgreich heruntergeladen"
else
    echo -e "\n** Fehler beim herunterladen der Datei cap_faq_de_pdf.pdf" >&2
fi

echo -e "-> warnings_codes_xls.xls ... \c"
if wget -q -O warnings_codes_xls.xls "https://www.dwd.de/DE/leistungen/gds/help/warnungen/warnings_codes_xls.xls?__blob=publicationFile"; then
    echo "Erfolgreich heruntergeladen"
else
    echo -e "\n** Fehler beim herunterladen der Datei warnings_codes_xls.xls" >&2
fi

exit 0
