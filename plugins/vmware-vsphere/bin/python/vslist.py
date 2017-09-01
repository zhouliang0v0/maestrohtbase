#!/usr/bin/env python
#
# HyperTask Enterprise developed by HyperTask Enterprise GmbH.
#
# All source code and content (c) Copyright 2014, HyperTask Enterprise GmbH unless specifically noted otherwise.
#
# This source code is released under the HyperTask Enterprise Server and Client License, unless otherwise agreed with HyperTask Enterprise GmbH.
# The latest version of this license can be found here: http://htvcenter-enterprise.com/license
#
# By using this software, you acknowledge having read this license and agree to be bound thereby.
#
#           http://htvcenter-enterprise.com
#
# Copyright 2014, HyperTask Enterprise GmbH <info@htvcenter-enterprise.com>
#

import atexit
import requests
from pyVim import connect
from pyVmomi import vmodl
from pyVmomi import vim

import libvmtask

from inspect import getmembers
from pprint import pprint
import argparse
import sys

import hashlib
import json
import random
import time

requests.packages.urllib3.disable_warnings()


def main():
	parser = argparse.ArgumentParser(description='vCenter login')
	parser.add_argument('-s', '--host', required=True, action='store', help='vSphere IP')
	parser.add_argument('-o', '--port', type=int, default=443, action='store', help='vSphere Port')
	parser.add_argument('-u', '--user', required=True, action='store', help='User name')
	parser.add_argument('-p', '--password', required=True, action='store', help='Password')
	parser.add_argument('-n', '--name', required=True, action='store', help='ESX Host name')

	args = parser.parse_args()

	try:
		service_instance = connect.SmartConnect(host=args.host,
												user=args.user,
												pwd=args.password,
												port=int(args.port))
		atexit.register(connect.Disconnect, service_instance)

		content=service_instance.RetrieveContent()
		pnics_used = ''
		pnics_avail = ''
		for Host in libvmtask.get_vim_objects(content,vim.HostSystem):
			if Host.name == args.name:
				#print getmembers(Host)
				for vswitch in Host.config.network.vswitch:
					#print getmembers(vswitch)
					sys.stdout.write("type=vs")
					sys.stdout.write("|name=" + str(vswitch.name))
					sys.stdout.write("|numPorts=" + str(vswitch.numPorts))
					sys.stdout.write("|numPortsAvailable=" + str(vswitch.numPortsAvailable))
					sys.stdout.write("|mtu=" + str(vswitch.mtu))
					pnics = ''

					# debugging
					#f = []
					#if vswitch.name == 'vSwitch0':
					#	f.append('vmnic0')
					#	f.append('vmnic1')
					#	f.append('vmnic2')
					#	f.append('vmnic3')
					#for vn in f:

					for vn in vswitch.spec.policy.nicTeaming.nicOrder.activeNic:
						pnics = pnics + vn + ','
						pnics_used = pnics_used + vn + ','
					pnics = pnics[:-1]
					sys.stdout.write("|pnic=" + str(pnics))
					print

					for portgroup in vswitch.portgroup:
						pgname = str(portgroup.split("-",2)[2])
						pgname = pgname.replace(" ", "@")
						sys.stdout.write("type=pg")
						sys.stdout.write("|name=" + pgname)
						sys.stdout.write("|vswitch=" + str(vswitch.name))
						sys.stdout.write("|numPorts=" + str(vswitch.numPorts))
						sys.stdout.write("|numPortsAvailable=" + str(vswitch.numPortsAvailable))
						sys.stdout.write("|mtu=" + str(vswitch.mtu))
						sys.stdout.write("|key=" + str(vswitch.key))
						print

				#print Host.config.network.pnic
				#f = []
				#f.append('vmnic0')
				#f.append('vmnic1')
				#f.append('vmnic2')
				#f.append('vmnic3')
				#for phys_net in f:
				#	pnics_avail = pnics_avail + str(phys_net) + ','

				for phys_net in Host.config.network.pnic:
					pnics_avail = pnics_avail + str(phys_net.device) + ','




		pnics_used = pnics_used[:-1]
		pnics_avail = pnics_avail[:-1]
		sys.stdout.write("type=pnic")
		sys.stdout.write("|pnic_used=" + str(pnics_used))
		sys.stdout.write("|pnic_avail=" + str(pnics_avail))
		print
		return 0

	except vmodl.MethodFault as error:
		print("ERROR: " + error.msg)
		sys.exit(1)

# Start program
if __name__ == "__main__":
	main()