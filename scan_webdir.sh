#!/bin/bash
# If you are on shared hosting and have the ability to schedule cron job,
# this may give you a warning when a site was hacked in your profile.
# It does a quick list of web folders in your profile then finds all the files and does a md5sum on it, 
# saving it to a text file.
# If it finds some files were changed, it will send you an email

# Change the following values to suit you
PROFILEDIR=/home/path/to/your/profile/public_html
OUTPUTFILE=/home/path/to/your/profile/scandirs/output.txt
OUTFOLDER=/home/path/to/your/profile/scandirs/scanresults
MAILTO=your@email.com

# Stop editing from here
DATE=`date +"%Y%m%d"`
YESTERDAY=`date +"%Y%m%d" --date="yesterday"`

# List directories in profile
ls ${PROFILEDIR} > ${OUTPUTFILE}

# process each directory
while read line
do
 touch ${OUTFOLDER}/${line}_${DATE}.txt
 # you'll notice I ignore jpg files in the find command
 /bin/nice -n 19 /bin/find ${PROFILEDIR}/${line} -type f \( -not -iname "*.jpg" \) -exec md5sum "{}" \; > ${OUTFOLDER}/${line}_${DATE}.txt
 /usr/bin/diff -C 0 ${OUTFOLDER}/${line}_${DATE}.txt ${OUTFOLDER}/${line}_${YESTERDAY}.txt > ${OUTFOLDER}/${line}_diffresults.txt
 if [ -s ${OUTFOLDER}/${line}_diffresults.txt ]; then
  # check file and email
  cat ${OUTFOLDER}/${line}_diffresults.txt | mailx -s "Diff results for ${line} - ${DATE}" ${MAILTO}
 fi
done <${OUTPUTFILE}
