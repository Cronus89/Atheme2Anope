/* RequiredLibraries: crypt */
/* Module for posix encryption.
 *
 * (C) 2003-2014 Anope Team
 * Contact us at team@anope.org
 *
 * This program is free but copyrighted software; see the file COPYING for
 * details.
 */

#include "module.h"

class EPosix : public Module
{
    std::string salt;
	bool use_salt;

	void NewRandomSalt()
	{
		salt = "$1$replaceme";
	}

        /* splits the appended salt from the password string so it can be used for the next encryption */
        /* password format:  <hashmethod>:<password>:<salt> */
	bool GetSaltFromPass(const Anope::string &password)
	{
		size_t pos = password.find("$");
		if (pos == Anope::string::npos)
			return false;
		size_t pos2 = password.find("$", pos + 1);
		if (pos2 == Anope::string::npos)
			return false;
		size_t pos3 = password.find("$", pos2 + 1);
		if (pos3 == Anope::string::npos)
			return false;

		std::stringstream buf;

		buf << password.substr(pos, (pos3 - pos));

	    if (!buf.str().length())
			buf << "$1$replaceme";

		salt = buf.str();
		return true;
	}

 public:
	EPosix(const Anope::string &modname, const Anope::string &creator) : Module(modname, creator, ENCRYPTION | VENDOR)
	{
		use_salt = false;
	}

	EventReturn OnEncrypt(const Anope::string &src, Anope::string &dest) anope_override
	{
		if (!use_salt)
			NewRandomSalt();
		else
			use_salt = false;

		std::stringstream buf;
		buf << "posix:" << crypt(src.c_str(), salt.c_str());

		Log(LOG_DEBUG_2) << "(enc_posix) hashed password from [" << src << "] to [" << buf.str() << "]";
		dest = buf.str();
		return EVENT_ALLOW;
	}

	void OnCheckAuthentication(User *, IdentifyRequest *req) anope_override
	{
		const NickAlias *na = NickAlias::Find(req->GetAccount());
		if (na == NULL)
			return;
		NickCore *nc = na->nc;

		size_t pos = nc->pass.find(':');
		if (pos == Anope::string::npos)
			return;
		Anope::string hash_method(nc->pass.begin(), nc->pass.begin() + pos);
		if (!hash_method.equals_cs("posix"))
			return;

		if (!GetSaltFromPass(nc->pass))
			return;
		use_salt = true;

		Anope::string buf;
		this->OnEncrypt(req->GetPassword(), buf);
		if (nc->pass.equals_cs(buf))
		{
			/* if we are NOT the first module in the list,
			 * we want to re-encrypt the pass with the new encryption
			 */
			if (ModuleManager::FindFirstOf(ENCRYPTION) != this)
				Anope::Encrypt(req->GetPassword(), nc->pass);
			req->Success(this);
		}
	}
};

MODULE_INIT(EPosix)
