#!/bin/bash

echo Lade Liste mit WarnCellID:
wget -O doc/cap_warncellids_csv.csv "http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_warncellids_csv.csv?__blob=publicationFile&v=4"
wget -O doc/cap_verwaltungsgebiete.zip "http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_verwaltungsgebiete_zip.zip?__blob=publicationFile&v=2"

echo Starte Download der restlichen Entwickler-Dokumentation und FAQ vom DWD:
wget -O doc/cap_dwd_profile_de_pdf.pdf "http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile&v=2"
wget -O doc/cap_faq_de_pdf.pdf "http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_faq_de_pdf.pdf?__blob=publicationFile&v=2"
wget -O doc/warnings_codes_xls.xls "http://www.dwd.de/DE/leistungen/gds/help/warnungen/warnings_codes_xls.xls?__blob=publicationFile&v=2"
exit 0